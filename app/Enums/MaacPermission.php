<?php

namespace App\Enums;

/**
 * Fine-grained MAAC platform permissions granted to a {@see MaacRole}.
 */
enum MaacPermission: string
{
    case ManagePlatform = 'platform:manage';
    case ManageApplication = 'application:manage';
    case ManageProject = 'project:manage';
    case ManageAgent = 'agent:manage';
    case ManageTool = 'tool:manage';
    case ManageCredential = 'credential:manage';
    case PublishAgent = 'agent:publish';
    case ApproveTool = 'tool:approve';
    case View = 'view';
    case ViewAudit = 'audit:view';
    case ReviewSecurity = 'security:review';
}
