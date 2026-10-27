<?php

return [
    'folders' => [
        'inbox' => 'Inbox',
        'archive' => 'Archive',
        'trash' => 'Trash',
        'fallback_slug' => 'folder',
    ],
    'flash' => [
        'message_sent' => 'Message sent.',
        'reply_sent' => 'Reply sent.',
        'conversation_moved' => 'Conversation moved.',
        'folder_created' => 'Folder created.',
        'folder_deleted' => 'Folder deleted. Conversations moved to Archive.',
        'conversations_moved' => '{0} No conversations moved.|{1} 1 conversation moved.|[2,*] :count conversations moved.',
        'conversations_trashed' => '{0} No conversations moved to Trash.|{1} 1 conversation moved to Trash.|[2,*] :count conversations moved to Trash.',
        'conversations_restored' => '{0} No conversations restored.|{1} 1 conversation restored.|[2,*] :count conversations restored.',
        'conversations_deleted' => '{0} No conversations deleted.|{1} 1 conversation permanently deleted.|[2,*] :count conversations permanently deleted.',
        'trash_emptied' => '{0} Trash is already empty.|{1} Trash emptied. 1 conversation permanently deleted.|[2,*] Trash emptied. :count conversations permanently deleted.',
        'marked_read' => '{0} No conversations updated.|{1} 1 conversation marked as read.|[2,*] :count conversations marked as read.',
        'marked_unread' => '{0} No conversations updated.|{1} 1 conversation marked as unread.|[2,*] :count conversations marked as unread.',
        'nothing_selected' => 'No conversations were selected.',
    ],
    'errors' => [
        'recipients_required' => 'Please specify at least one recipient.',
        'no_valid_recipients' => 'No valid recipients found.',
        'blocked_recipients' => 'These users are not accepting private messages: :users.',
        'no_conversations_selected' => 'Select at least one conversation first.',
        'invalid_bulk_action' => 'That bulk action is not available.',
    ],
    'actions' => [
        'move' => 'Move',
        'move_to' => 'Move to',
        'trash' => 'Trash',
        'restore' => 'Restore',
        'delete_permanently' => 'Delete permanently',
        'mark_read' => 'Mark as read',
        'mark_unread' => 'Mark as unread',
        'empty_trash' => 'Empty Trash',
        'select_all' => 'Select all',
        'clear_selection' => 'Clear selection',
        'selected_count' => ':count selected',
        'row_actions' => 'Conversation actions',
    ],
    'confirm' => [
        'delete_permanently' => 'Permanently delete the selected conversation(s)? This cannot be undone.',
        'empty_trash' => 'Permanently delete every conversation in Trash? This cannot be undone.',
    ],
    'trash' => [
        'retention_notice' => 'Conversations in Trash are permanently deleted after :days days.',
    ],
];
