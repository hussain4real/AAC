<?php

namespace App\Enums;

/**
 * Fine-grained MAACC platform permissions granted to a {@see MaaccRole}.
 */
enum MaaccPermission: string
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
