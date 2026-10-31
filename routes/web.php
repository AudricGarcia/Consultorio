<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AppointmentReminderController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\CashController;
use App\Http\Controllers\ClinicalMediaController;
use App\Http\Controllers\ClinicalRecordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\OdontogramController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TreatmentCatalogController;
use App\Http\Controllers\TreatmentPlanController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VoiceAssistantController;
use App\Http\Controllers\VoiceCommandHistoryController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login')->name('login.store');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:6,1')->name('password.update');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/reports/financial', [ReportController::class, 'financial'])->name('reports.financial');
    Route::get('/reports/audit', [ReportController::class, 'audit'])->name('reports.audit');
    Route::post('/assistant/voice', VoiceAssistantController::class)->middleware('throttle:30,1')->name('assistant.voice');
    Route::get('/assistant/voice-history', [VoiceCommandHistoryController::class, 'index'])->name('assistant.voice-history');
    Route::put('/assistant/voice-history/{voiceCommandLog}/correction', [VoiceCommandHistoryController::class, 'correct'])->name('assistant.voice-history.correct');
    Route::post('appointment-reminders/{appointmentReminder}/acknowledge', [AppointmentReminderController::class, 'acknowledge'])->name('appointment-reminders.acknowledge');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::resource('patients', PatientController::class);
    Route::resource('appointments', AppointmentController::class);
    Route::resource('clinical-records', ClinicalRecordController::class);
    Route::post('clinical-records/{clinicalRecord}/validate', [ClinicalRecordController::class, 'validateRecord'])->name('clinical-records.validate');
    Route::post('clinical-records/{clinicalRecord}/media', [ClinicalMediaController::class, 'store'])->name('clinical-records.media.store');
    Route::get('clinical-media/{clinicalMedia}', [ClinicalMediaController::class, 'show'])->name('clinical-media.show');
    Route::resource('users', UserController::class)->except(['show', 'destroy']);
    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::resource('treatment-plans', TreatmentPlanController::class);
    Route::post('treatment-plans/{treatmentPlan}/items/{item}/sessions', [TreatmentPlanController::class, 'storeSession'])->name('treatment-plans.sessions.store');
    Route::resource('treatment-catalog', TreatmentCatalogController::class)->except(['show', 'destroy']);
    Route::post('treatment-plans/{treatmentPlan}/accept', [TreatmentPlanController::class, 'accept'])->name('treatment-plans.accept');
    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('payments/patients/search', [PaymentController::class, 'searchPatients'])->name('payments.patients.search');
    Route::get('payments/patients/{patient}', [PaymentController::class, 'patientStatement'])->name('payments.patients.statement');
    Route::get('patients/{patient}/payments/create', [PaymentController::class, 'create'])->name('patients.payments.create');
    Route::post('patients/{patient}/payments', [PaymentController::class, 'store'])->name('patients.payments.store');
    Route::post('payments/{payment}/refund', [PaymentController::class, 'refund'])->name('payments.refund');
    Route::get('cash', [CashController::class, 'index'])->name('cash.index');
    Route::get('cash/open', [CashController::class, 'open'])->name('cash.open');
    Route::post('cash', [CashController::class, 'storeSession'])->name('cash.store');
    Route::post('cash/{cashSession}/close', [CashController::class, 'close'])->name('cash.close');
    Route::post('cash/{cashSession}/reopen', [CashController::class, 'reopen'])->name('cash.reopen');
    Route::post('cash/expenses', [CashController::class, 'expense'])->name('cash.expenses.store');
    Route::post('cash/expenses/{cashMovement}/approve', [CashController::class, 'approveExpense'])->name('cash.expenses.approve');
    Route::post('cash/expenses/{cashMovement}/reject', [CashController::class, 'rejectExpense'])->name('cash.expenses.reject');
    Route::resource('inventory', InventoryController::class)->parameters(['inventory' => 'inventoryItem']);
    Route::post('inventory/receive', [InventoryController::class, 'receive'])->name('inventory.receive');
    Route::post('inventory/movements', [InventoryController::class, 'move'])->name('inventory.movements.store');
    Route::post('treatment-sessions/{treatmentSession}/materials', [InventoryController::class, 'consumeForSession'])->name('treatment-sessions.materials.store');
    Route::post('patients/{patient}/odontogram/{finding}/void', [OdontogramController::class, 'annul'])->name('patients.odontogram.void');
    Route::resource('suppliers', SupplierController::class)->except(['show']);
    Route::resource('purchase-orders', PurchaseOrderController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');
    Route::post('purchase-orders/{purchaseOrder}/payments', [PurchaseOrderController::class, 'pay'])->name('purchase-orders.payments.store');
    Route::resource('patients.odontogram', OdontogramController::class)
        ->names('patients.odontogram')
        ->parameters(['odontogram' => 'finding']);
});
