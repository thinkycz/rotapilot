# Agent Stream Navigation Loss

## Symptom

When a manager sent a message and left the agent screen before streaming finished, reopening the conversation could show a blank chat page.

## Evidence

`AgentStreamController` created the conversation row before streaming so tools had a conversation id. The Laravel AI `RememberConversation` middleware stores the user and assistant messages only after the stream completes. If the browser leaves early, the stream can stop before those completion callbacks run.

The frontend also showed the empty state only when no conversation was selected, so an existing conversation with no visible messages rendered as an empty message list.

## Root Cause

The app had durable conversation creation at stream start, but durable message creation only at stream completion.

## Fix

`AgentStreamController` now writes a provisional user message after the stream has built its model context and before the first SSE event is yielded. If the stream completes normally, the canonical Laravel AI message exists and the provisional row is deleted. If the stream is interrupted, the manager's message remains visible when the conversation is reopened.

The agent page now shows the empty state whenever there are no visible messages and no orphan proposals, including older empty conversations.

## Recurrence Note

For streaming flows, any user action that must survive navigation should be persisted before the first client-visible stream event, not only in completion callbacks.
