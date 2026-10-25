<?php

return [
    // How many of the latest notifications the header bell dropdown lists. The
    // rest are one click away on the "View all notifications" page, which is
    // linked from the top of the dropdown. Keep it small: each entry is
    // several lines tall.
    'bell_dropdown_limit' => (int) env('NOTIFICATIONS_BELL_DROPDOWN_LIMIT', 5),
];
