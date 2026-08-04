<?php

namespace App\Enums;

enum AuditAction: string
{
    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    case AuthenticationSucceeded =
        'auth.login.succeeded';

    case AuthenticationLoggedOut =
        'auth.logout';

    case MandatoryPasswordChanged =
        'auth.password.changed';

    case TwoFactorSetupStarted =
        'auth.two-factor.setup-started';

    case TwoFactorConfirmed =
        'auth.two-factor.confirmed';

    case TwoFactorDisabled =
        'auth.two-factor.disabled';

    case TwoFactorRecoveryCodesRegenerated =
        'auth.two-factor.recovery-codes-regenerated';

    case TwoFactorChallengeFailed =
        'auth.two-factor.challenge-failed';

    /*
    |--------------------------------------------------------------------------
    | User administration
    |--------------------------------------------------------------------------
    */

    case UserCreated =
        'administration.user.created';

    case UserUpdated =
        'administration.user.updated';

    case UserActivated =
        'administration.user.activated';

    case UserDeactivated =
        'administration.user.deactivated';

    case UserPasswordReset =
        'administration.user.password-reset';

    case UserRolesChanged =
        'administration.user.roles-changed';

    case OperatorAccountLinked =
        'administration.operator.account-linked';

    case OperatorAccountUnlinked =
        'administration.operator.account-unlinked';

    case OperatorAssignmentCreated =
        'administration.operator-assignment.created';

    case OperatorAssignmentUpdated =
        'administration.operator-assignment.updated';

    case OperatorAssignmentEnded =
        'administration.operator-assignment.ended';

    /*
    |--------------------------------------------------------------------------
    | Role administration
    |--------------------------------------------------------------------------
    */

    case RolePermissionsChanged =
        'administration.role.permissions-changed';

    /*
    |--------------------------------------------------------------------------
    | ERP synchronization
    |--------------------------------------------------------------------------
    */

    case SynchronizationStarted =
        'erp.synchronization.started';

    case SynchronizationCompleted =
        'erp.synchronization.completed';

    case SynchronizationFailed =
        'erp.synchronization.failed';

    /*
    |--------------------------------------------------------------------------
    | Configuration
    |--------------------------------------------------------------------------
    */

    case SystemSettingsUpdated =
        'administration.system-settings.updated';

    case ErpSettingsUpdated =
        'administration.erp-settings.updated';
        /*
    |--------------------------------------------------------------------------
    | Production execution
    |--------------------------------------------------------------------------
    */

    case ProductionOrderCreated =
        'production.order.created';

    case ProductionOrderStatusChanged =
        'production.order.status-changed';

    case ProductionBatchCreated =
        'production.batch.created';

    case ProductionBatchStatusChanged =
        'production.batch.status-changed';

    case ProductionRecordCreated =
        'production.record.created';

    case ProductionRecordSubmitted =
        'production.record.submitted';

    case ProductionRecordValidated =
        'production.record.validated';

    case ProductionRecordRejected =
        'production.record.rejected';

    case ProductionEventReported =
        'production.event.reported';

    case ProductionEventResolved =
        'production.event.resolved';

    case ProductionReportGenerated =
        'reporting.production.generated';

    /*
    |--------------------------------------------------------------------------
    | Guarded AI explanations
    |--------------------------------------------------------------------------
    */

    case AiExplanationGenerated =
        'ai.explanation.generated';

    case AiExplanationFailed =
        'ai.explanation.failed';
}
