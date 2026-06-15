<?php

declare(strict_types=1);

namespace App\Enums;

enum SuspiciousEventType: string
{
    case TabSwitch = 'tab_switch';
    case VisibilityChange = 'visibility_change';
    case FullscreenExit = 'fullscreen_exit';
    case CopyAttempt = 'copy_attempt';
    case PasteDetected = 'paste_detected';
}
