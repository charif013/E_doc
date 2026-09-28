@php
    $commentId = $id ?? 'document_comment';
    $commentLabel = $label ?? 'ความคิดเห็น';
    $commentRows = $rows ?? 2;
@endphp

<div class="mb-3">
    <label for="{{ $commentId }}" class="form-label fw-bold text-dark">
        <i class="fas fa-comment-dots me-1 text-primary"></i>
        {{ $commentLabel }} <span class="text-danger">*</span>
    </label>
    <div class="d-flex flex-wrap gap-2 mb-2" role="group" aria-label="ความคิดเห็นสำเร็จรูป">
        @foreach(['เห็นชอบ', 'รับทราบ', 'อนุมัติ'] as $presetComment)
            <button type="button"
                    class="btn btn-sm btn-outline-primary rounded-pill px-3"
                    data-comment="{{ $presetComment }}"
                    onclick="document.getElementById('{{ $commentId }}').value = this.dataset.comment; document.getElementById('{{ $commentId }}').focus();">
                {{ $loop->iteration }}. {{ $presetComment }}
            </button>
        @endforeach
    </div>
    <textarea id="{{ $commentId }}" name="comment" class="form-control" rows="{{ $commentRows }}"
              maxlength="1000" required placeholder="เลือกความคิดเห็นสำเร็จรูป หรือพิมพ์ความคิดเห็น...">{{ old('comment') }}</textarea>
    <div class="form-text">ต้องระบุความคิดเห็นก่อนส่งเอกสาร</div>
    @error('comment')<div class="text-danger small fw-bold mt-1">{{ $message }}</div>@enderror
</div>
