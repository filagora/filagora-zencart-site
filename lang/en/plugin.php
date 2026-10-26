<?php

return [
    'flash' => [
        'initial_release_live' => 'Your plugin and initial release are now live.',
        'initial_release_pending_review' => 'Your plugin and initial release have been submitted and are pending review.',
        'release_live' => 'Your release is now live.',
        'release_pending_review' => 'Your release has been submitted and is pending review.',
        'release_zc_versions_updated' => 'Compatible Zen Cart versions updated.',
        'plugin_updated' => 'Plugin updated.',
        'subscribed' => 'Plugin subscribed.',
        'unsubscribed' => 'Plugin unsubscribed.',
        'subscription_removed' => 'Subscription removed.',
    ],
    'errors' => [
        'download_unavailable' => 'This release file is currently unavailable. Please try again later.',
        'download_entitlement_required' => 'Downloading this plugin requires the :entitlement entitlement.',
        'version_already_exists' => 'This plugin already has a release with that version number. Choose a different version.',
        'version_invalid' => 'Version must be a number like 1.0.1 (an optional leading "v" is fine).',
        'version_folder_mismatch' => 'The version you entered (:typed) doesn\'t match the version folder inside the ZIP (:folder). Zen Cart reports the folder name as the installed version, so they must be the same.',
    ],
    'release_supersede' => [
        'warning_title' => '{1} Version :versions is still awaiting review|[2,*] Versions :versions are still awaiting review',
        'warning_body' => '{1} If you submit this new release, the pending release is rejected permanently and moderators review this upload instead. This cannot be undone.|[2,*] If you submit this new release, all the pending releases are rejected permanently and moderators review this upload instead. This cannot be undone.',
        'confirm_label' => '{1} Reject version :versions permanently and submit this release instead.|[2,*] Reject versions :versions permanently and submit this release instead.',
        'required' => '{1} Version :versions of this plugin is still awaiting review. Confirm that this upload should replace it.|[2,*] Versions :versions of this plugin are still awaiting review. Confirm that this upload should replace them.',
        'note' => 'Superseded by release v:version, uploaded by :user.',
    ],
    'reports' => [
        'already_reported' => 'You have already reported this plugin.',
        'submitted' => 'Plugin reported. Staff will review it.',
    ],
    'support_thread' => [
        'default_body' => 'Official support thread for :plugin.',
    ],
    'moderation' => [
        'defaults' => [
            'author' => 'author',
        ],
        'flash' => [
            'plugin_approved' => 'Plugin ":plugin" approved.',
            'plugin_rejected' => 'Plugin rejected and author notified.',
            'deleted' => 'Deleted :plugins plugin(s) and :releases release(s).',
            'release_approved' => 'Release for plugin ":plugin" approved.',
            'release_rejected' => 'Release for plugin ":plugin" rejected and author notified.',
            'auto_publish_plugin_author_enabled' => 'Auto-publish enabled for plugin author ":author".',
            'auto_publish_release_author_enabled' => 'Auto-publish enabled for release author ":author".',
        ],
        'errors' => [
            'plugin_author_missing' => 'This plugin does not have an author account to update.',
            'release_author_missing' => 'This release does not have an author account to update.',
        ],
        'validation' => [
            'rejection_note_required' => 'A rejection note is required.',
            'release_plugin_mismatch' => 'Release does not belong to this plugin.',
            'invalid_action' => 'Invalid moderation action.',
        ],
        'notes' => [
            'plugin_approved' => 'Approved plugin.',
            'plugin_rejected' => 'Rejected plugin.',
            'release_approved' => 'Approved release v:version.',
            'release_rejected' => 'Rejected release v:version.',
            'release_superseded' => 'Rejected release v:version automatically: a newer upload replaced it before review.',
        ],
    ],
];
