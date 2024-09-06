<?php

namespace App\Observers;

use App\Models\User;
use Wazza\SyncModelToCrm\Http\Controllers\CrmController;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class UserObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        echo ('create...');
        (new CrmController())
            ->setModel($user)
            ->setAttemptCreate()
            ->execute(true);
        echo ('created...');
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        echo ('update...');
        (new CrmController())
            ->setModel($user)
            ->setAttemptUpdate()
            ->execute(true);
        echo ('updated...');
    }

    /**
     * Handle the User "deleted" event.
     * Run when a user is soft-deleted.
     */
    public function deleted(User $user)
    {
        echo ('delete...');
        (new CrmController())
            ->setModel($user)
            ->setAttemptDelete()
            ->execute();
        echo ('deleted...');
    }

    /**
     * Handle the User "restored" event.
     * Soft-delete has been reversed.
     */
    public function restored(User $user): void
    {
        echo ('restore...');
        (new CrmController())
            ->setModel($user)
            ->setAttemptRestore()
            ->execute();
        echo ('restored...');
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        echo ('forceDeleted...');
    }

    /**
     * Handle the User "saved" event.
     *
     */
    public function saved(User $user): void
    {
        // echo ('saving...');
        // (new CrmController())
        //     ->setModel($user)
        //     ->setAttemptAll() // open for anything...
        //     ->execute(true);
        // echo ('saved...');
    }
}
