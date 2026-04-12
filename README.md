# laravel-messaging-playground

A **reference Laravel application** that shows how the **[Laravel Messaging](https://github.com/Phunky/laravel-messaging)** family of packages fits together in a real UI. It is not a generic chat starter kit: it is a **living demo** you can run locally to see conversations, groups, reactions, attachments, read receipts, and live updates end to end.


| Repository                                                                               | What you see in the playground                                                                |
| ---------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------- |
| [laravel-messaging](https://github.com/Phunky/laravel-messaging)                         | Conversations, participants, messages, messaging events (delivery/read), extension points.    |
| [laravel-messaging-groups](https://github.com/Phunky/laravel-messaging-groups)           | Group threads, membership, and group-backed conversations in the inbox.                       |
| [laravel-messaging-reactions](https://github.com/Phunky/laravel-messaging-reactions)     | Emoji reactions on messages in both DMs and groups.                                           |
| [laravel-messaging-attachments](https://github.com/Phunky/laravel-messaging-attachments) | Rich attachments (e.g. images, documents, voice notes) in the thread and viewers where wired. |


### What you can find built here

- **Inbox + thread**: pick a conversation from the list; on small screens the layout switches between list and thread (“mobile stack”) like common messaging apps.
- **Direct messages**: one seeded DM per peer with the demo user so you can scroll history and compare with group threads.
- **Groups**: several seeded groups with varied membership and message volume.
- **Reactions**: sample reactions on a subset of messages to exercise the reactions package in context.
- **Read / unread**: the seeder writes `message.received` and `message.read` events and leaves a few incoming messages without a read event so the inbox can show realistic unread state.
- **Real time**: with Reverb running and Echo configured, opening the same conversation in two browsers shows new messages and relevant events without a manual refresh.

