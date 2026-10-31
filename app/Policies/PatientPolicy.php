<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('patients.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Patient $patient): bool
    {
        if (! $user->hasPermission('patients.view')) {
            return false;
        }

        if (! $user->hasPermission('clinical.view') || $user->hasRole('owner')) {
            return true;
        }

        return (int) $patient->created_by === (int) $user->id
            || $patient->appointments()->where('professional_id', $user->id)->exists()
            || $patient->clinicalRecords()->where('professional_id', $user->id)->exists();
    }

    public function paymentView(User $user, Patient $patient): bool
    {
        if (! $user->hasPermission('patients.view') && ! $user->hasPermission('patients.payment_lookup')) {
            return false;
        }

        if (! $user->hasPermission('clinical.view') || $user->hasRole('owner')) {
            return true;
        }

        return (int) $patient->created_by === (int) $user->id
            || $patient->appointments()->where('professional_id', $user->id)->exists()
            || $patient->clinicalRecords()->where('professional_id', $user->id)->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('patients.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Patient $patient): bool
    {
        return $user->hasPermission('patients.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Patient $patient): bool
    {
        return $user->hasRole('owner');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Patient $patient): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Patient $patient): bool
    {
        return false;
    }
}
