<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class Document extends V2Model
{
    use SoftDeletes;

    protected $casts = [
        'document_date' => 'date',
        'receive_date' => 'date',
    ];

    public function type(): BelongsTo { return $this->belongsTo(DocumentType::class, 'document_type_id'); }
    public function category(): BelongsTo { return $this->belongsTo(DocumentCategory::class, 'document_category_id'); }
    public function priority(): BelongsTo { return $this->belongsTo(DocumentPriority::class, 'priority_id'); }
    public function confidentiality(): BelongsTo { return $this->belongsTo(DocumentConfidentiality::class, 'confidentiality_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function files(): HasMany { return $this->hasMany(DocumentFile::class); }
    public function assignments(): HasMany { return $this->hasMany(DocumentAssignment::class); }
    public function accessRequests(): HasMany { return $this->hasMany(DocumentAccessRequest::class); }
    public function workflow(): HasOne { return $this->hasOne(WorkflowInstance::class); }
    public function numberAllocation(): HasOne { return $this->hasOne(NumberAllocation::class); }
    public function routes(): HasManyThrough
    {
        return $this->hasManyThrough(WorkflowStep::class, WorkflowInstance::class,
            'document_id', 'workflow_instance_id', 'id', 'id')->orderBy('step_order');
    }

    /** ระบุว่า workflow กำลังรอลายเซ็นจากผู้พิจารณาคนสุดท้ายหรือไม่ */
    public function isAtFinalApprovalStep(): bool
    {
        if (strtoupper((string) $this->status) !== 'IN_REVIEW') {
            return false;
        }

        $routes = $this->relationLoaded('routes') ? $this->routes : $this->routes()->get();
        $currentStep = (int) $this->current_step;
        $currentRoute = $routes->firstWhere('step_order', $currentStep);
        $routeStatus = $currentRoute?->status instanceof \BackedEnum
            ? $currentRoute->status->value
            : (string) $currentRoute?->status;

        return $currentStep > 1
            && $currentRoute
            && strtoupper((string) $routeStatus) === 'PENDING'
            && $currentStep === (int) $routes->max('step_order');
    }

    protected function docType(): Attribute { return Attribute::get(fn () => strtolower((string) $this->type?->code)); }
    protected function docNumber(): Attribute { return Attribute::get(fn () => $this->document_number); }
    protected function docDate(): Attribute { return Attribute::get(fn () => $this->document_date); }
    protected function docFrom(): Attribute { return Attribute::get(fn () => $this->sender_name); }
    protected function docTo(): Attribute { return Attribute::get(fn () => $this->recipient_name); }
    protected function docTypeCategory(): Attribute { return Attribute::get(fn () => $this->category?->name); }
    protected function docSpeed(): Attribute { return Attribute::get(fn () => $this->priority?->name); }
    protected function docSecret(): Attribute { return Attribute::get(fn () => $this->confidentiality?->name); }
    protected function currentStep(): Attribute { return Attribute::get(fn () => $this->workflow?->current_step); }
    protected function runningNumber(): Attribute { return Attribute::get(fn () => $this->numberAllocation?->running_number); }
    protected function formattedDocNumber(): Attribute { return Attribute::get(fn () => $this->doc_type === 'incoming'
        ? $this->document_number
        : ($this->numberAllocation?->formatted_number ?: $this->document_number)); }
    protected function formattedReceiveNumber(): Attribute { return Attribute::get(fn () => $this->numberAllocation?->formatted_number ?: $this->receive_number); }
    protected function assignedUserId(): Attribute { return Attribute::get(fn () => $this->latestAssignment()?->assigned_user_id); }
    protected function assignedTo(): Attribute { return Attribute::get(fn () => $this->latestAssignment()?->unit?->name); }
    protected function assignmentStatus(): Attribute { return Attribute::get(function () {
        $assignment = $this->latestAssignment();
        if (! $assignment) { return null; }
        $status = $assignment->status instanceof \BackedEnum ? $assignment->status->value : (string) $assignment->status;
        if ($status === 'ACKNOWLEDGED') { return 'accepted'; }
        if ($status === 'PENDING' && $assignment->delegated_by) { return 'delegated'; }
        return strtolower($status);
    }); }
    protected function assignedAt(): Attribute { return Attribute::get(fn () => $this->latestAssignment()?->assigned_at); }
    protected function acknowledgedAt(): Attribute { return Attribute::get(fn () => $this->latestAssignment()?->acknowledged_at); }
    protected function assignee(): Attribute { return Attribute::get(fn () => $this->latestAssignment()?->assignee); }
    protected function delegator(): Attribute { return Attribute::get(function () {
        $assignment = $this->latestAssignment();
        return $assignment?->delegated_by ? User::find($assignment->delegated_by) : null;
    }); }
    protected function referenceDoc(): Attribute { return Attribute::get(fn () => $this->reference_text); }
    protected function rejectReason(): Attribute { return Attribute::get(fn () => $this->rejection_reason); }

    protected function attachmentPath(): Attribute { return Attribute::get(fn () => $this->fileValue('MAIN', 'file_path')); }
    protected function signedPath(): Attribute { return Attribute::get(fn () => $this->fileValue('SIGNED', 'file_path')); }
    protected function externalAttachmentPath(): Attribute { return Attribute::get(fn () => $this->fileValue('EXTERNAL', 'file_path')); }
    protected function externalUrl(): Attribute { return Attribute::get(fn () => $this->fileValue('EXTERNAL', 'external_url')); }
    protected function externalOriginalName(): Attribute { return Attribute::get(fn () => $this->fileValue('EXTERNAL', 'original_name')); }
    protected function externalMimeType(): Attribute { return Attribute::get(fn () => $this->fileValue('EXTERNAL', 'mime_type')); }
    protected function externalFileSize(): Attribute { return Attribute::get(fn () => $this->fileValue('EXTERNAL', 'file_size')); }
    protected function externalSha256(): Attribute { return Attribute::get(fn () => $this->fileValue('EXTERNAL', 'sha256')); }
    protected function externalDownloadedAt(): Attribute { return Attribute::get(fn () => $this->fileValue('EXTERNAL', 'downloaded_at')); }
    protected function externalDownloadError(): Attribute { return Attribute::get(fn () => $this->fileValue('EXTERNAL', 'download_error')); }

    protected function creatorSignature(): Attribute { return Attribute::get(fn () => $this->evidenceValue('CREATED', 'signature')); }
    protected function supervisorSignature(): Attribute { return Attribute::get(fn () => $this->evidenceValue('SUPERVISOR_APPROVED', 'signature')); }
    protected function supervisorApprovedAt(): Attribute { return Attribute::get(fn () => $this->evidenceValue('SUPERVISOR_APPROVED', 'acted_at')); }
    protected function supervisorComment(): Attribute { return Attribute::get(fn () => $this->evidenceValue('SUPERVISOR_APPROVED', 'comment')); }
    protected function supervisor(): Attribute { return Attribute::get(fn () => $this->evidenceValue('SUPERVISOR_APPROVED', 'actor')); }
    protected function paladSignature(): Attribute { return Attribute::get(fn () => $this->evidenceValue('PALAD_APPROVED', 'signature')); }
    protected function paladApprovedAt(): Attribute { return Attribute::get(fn () => $this->evidenceValue('PALAD_APPROVED', 'acted_at')); }
    protected function paladComment(): Attribute { return Attribute::get(fn () => $this->evidenceValue('PALAD_APPROVED', 'comment')); }
    protected function palad(): Attribute { return Attribute::get(fn () => $this->evidenceValue('PALAD_APPROVED', 'actor')); }
    protected function nayokSignature(): Attribute { return Attribute::get(fn () => $this->evidenceValue('EXECUTIVE_APPROVED', 'signature')); }
    protected function nayokApprovedAt(): Attribute { return Attribute::get(fn () => $this->evidenceValue('EXECUTIVE_APPROVED', 'acted_at')); }
    protected function nayokComment(): Attribute { return Attribute::get(fn () => $this->evidenceValue('EXECUTIVE_APPROVED', 'comment')); }
    protected function nayok(): Attribute { return Attribute::get(fn () => $this->evidenceValue('EXECUTIVE_APPROVED', 'actor')); }

    private function fileValue(string $type, string $column): mixed
    {
        $file = $this->relationLoaded('files')
            ? $this->files->where('file_type', $type)->sortByDesc('version_no')->first()
            : $this->files()->where('file_type', $type)->latest('version_no')->first();

        return $file?->{$column};
    }

    private function latestAssignment(): ?DocumentAssignment
    {
        if ($this->relationLoaded('assignments')) {
            return $this->assignments
                ->reject(function ($assignment) {
                    $status = $assignment->status instanceof \BackedEnum ? $assignment->status->value : (string) $assignment->status;
                    return strtoupper($status) === 'CANCELED';
                })
                ->sortByDesc('id')
                ->first();
        }

        return $this->assignments()->with(['assignee', 'unit'])
            ->where('status', '!=', 'CANCELED')->latest('id')->first();
    }

    private function evidenceValue(string $action, string $field): mixed
    {
        $steps = $this->relationLoaded('routes') ? $this->routes : $this->routes()->with('evidence.actor')->get();
        $evidence = $steps->flatMap(function ($step) {
            return $step->relationLoaded('evidence') ? $step->evidence : $step->evidence()->with('actor')->get();
        })->where('action', $action)->sortByDesc('acted_at')->first();
        if (! $evidence) { return null; }
        if ($field === 'actor') { return $evidence->actor; }
        if ($field === 'signature') {
            if (! $evidence->signature_path || ! Storage::disk('local')->exists($evidence->signature_path)) { return null; }
            return 'data:image/png;base64,'.base64_encode(Storage::disk('local')->get($evidence->signature_path));
        }

        return $evidence->{$field};
    }
}
