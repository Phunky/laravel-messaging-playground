<?php

namespace Phunky\Actions\Chat;

use Illuminate\Pagination\Cursor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Phunky\LaravelMessaging\Facades\Messenger;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessagingGroups\Group;
use Phunky\LaravelMessagingReactions\Reaction;
use Phunky\Models\User;
use Phunky\Support\ConversationListMessagePreview;

final class ListConversationInboxRows
{
    /**
     * @return array{rows: list<array<string, mixed>>, next_cursor: ?string, has_more: bool}
     */
    public function __invoke(User $user, ?string $cursor = null, int $perPage = 20): array
    {
        $conversationTable = (new (config('messaging.models.conversation')))->getTable();
        $messagesTable = messaging_table('messages');
        $reactionsTable = messaging_table('reactions');

        $lastActivitySql = <<<SQL
            CASE
                WHEN (SELECT MAX(r.updated_at) FROM {$reactionsTable} r
                      INNER JOIN {$messagesTable} rm ON rm.id = r.message_id
                      WHERE rm.conversation_id = {$conversationTable}.id)
                     > (SELECT MAX(ms.sent_at) FROM {$messagesTable} ms
                        WHERE ms.conversation_id = {$conversationTable}.id)
                THEN (SELECT MAX(r2.updated_at) FROM {$reactionsTable} r2
                      INNER JOIN {$messagesTable} rm2 ON rm2.id = r2.message_id
                      WHERE rm2.conversation_id = {$conversationTable}.id)
                ELSE (SELECT MAX(ms2.sent_at) FROM {$messagesTable} ms2
                      WHERE ms2.conversation_id = {$conversationTable}.id)
            END
            SQL;

        $query = Messenger::conversationsFor($user)
            ->with(['participants.messageable', 'latestMessage.attachments'])
            ->selectSub($lastActivitySql, 'last_activity_at')
            ->reorder()
            ->orderByDesc('last_activity_at')
            ->orderByDesc($conversationTable.'.id');

        if ($cursor === null || $cursor === '') {
            $page = $query->cursorPaginate($perPage);
        } else {
            $page = $query->cursorPaginate($perPage, ['*'], 'cursor', Cursor::fromEncoded($cursor));
        }

        $conversations = collect($page->items());
        $ids = $conversations->pluck('id');

        $groups = Group::query()->whereIn('conversation_id', $ids)->get()->keyBy('conversation_id');
        $reactions = $this->loadLastReactions($ids);

        $rows = [];
        foreach ($conversations as $conversation) {
            if (! $conversation instanceof Conversation) {
                continue;
            }

            $rows[] = $this->formatRow(
                $user,
                $conversation,
                $groups->get($conversation->id),
                $reactions->get($conversation->id),
            );
        }

        return [
            'rows' => $rows,
            'next_cursor' => $page->nextCursor()?->encode(),
            'has_more' => $page->hasMorePages(),
        ];
    }

    /**
     * @param  Collection<int, mixed>  $ids
     * @return Collection<int|string, Reaction>
     */
    private function loadLastReactions(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        $messagesTable = messaging_table('messages');
        $reactionsTable = messaging_table('reactions');

        return Reaction::query()
            ->select("{$reactionsTable}.*")
            ->join($messagesTable, "{$messagesTable}.id", '=', "{$reactionsTable}.message_id")
            ->whereIn("{$messagesTable}.conversation_id", $ids->all())
            ->with(['participant.messageable', 'message'])
            ->orderByDesc("{$reactionsTable}.updated_at")
            ->get()
            ->unique(fn (Reaction $r) => $r->message->conversation_id)
            ->keyBy(fn (Reaction $r) => $r->message->conversation_id);
    }

    /**
     * @return array{conversation_id: int, title: string, subtitle: string, is_group: bool, updated_at: ?string, unread_count: int, other_participant_ids: list<int>}
     */
    private function formatRow(
        User $user,
        Conversation $conversation,
        ?Group $group,
        ?Reaction $lastReaction = null,
    ): array {
        $title = 'Conversation';
        $subtitle = '';
        $isGroup = $group !== null;

        $otherParticipantIds = $conversation->participants
            ->map(fn ($p) => $p->messageable)
            ->filter()
            ->filter(fn ($m) => $m instanceof User && (string) $m->getKey() !== (string) $user->getKey())
            ->map(fn (User $m) => (int) $m->getKey())
            ->values()
            ->all();

        if ($isGroup) {
            $title = $group->name;
        } else {
            $other = $conversation->participants
                ->map(fn ($p) => $p->messageable)
                ->filter()
                ->first(fn ($m) => $m && (string) $m->getKey() !== (string) $user->getKey());

            if ($other instanceof User) {
                $title = $other->name;
            }
        }

        $lastMessage = $conversation->latestMessage;

        $reactionIsLatest = $lastReaction
            && (! $lastMessage || $lastReaction->updated_at->greaterThan($lastMessage->sent_at));

        if ($reactionIsLatest) {
            $name = $lastReaction->participant->messageable?->name ?? 'Someone';
            $body = Str::limit($lastReaction->message->body, 30);
            $subtitle = "{$name} reacted {$lastReaction->reaction} to \"{$body}\"";
        } elseif ($lastMessage instanceof Message) {
            $subtitle = ConversationListMessagePreview::subtitle($lastMessage);
        }

        $activityAt = $conversation->last_activity_at
            ? Carbon::parse($conversation->last_activity_at)
            : ($lastMessage?->sent_at ?? $conversation->updated_at);

        $updatedIso = $activityAt?->toIso8601String();

        return [
            'conversation_id' => (int) $conversation->id,
            'title' => $title,
            'subtitle' => $subtitle,
            'is_group' => $isGroup,
            'updated_at' => $updatedIso,
            'unread_count' => (int) ($conversation->unread_count ?? 0),
            'other_participant_ids' => $otherParticipantIds,
        ];
    }
}
