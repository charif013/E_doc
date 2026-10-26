{{-- Partial: ส่วนเลือกผู้พิจารณาและกำหนดลำดับเส้นทางเอกสาร --}}
@php
    $selectedRoutes = old('routing_users', []);
    $routeUsersByDepartment = $users
        ->groupBy(fn ($routeUser) => $routeUser->department ?: 'ไม่ระบุสำนัก/กอง')
        ->sortKeys();
    $routePresets = $routePresets ?? collect();
@endphp

<div class="card border-0 shadow-sm mb-4 route-selector"
     data-route-selector
     data-presets="{{ $routePresets->values()->toJson(JSON_UNESCAPED_UNICODE) }}"
     data-store-url="{{ route('documents.route_presets.store') }}"
     data-delete-url-template="{{ route('documents.route_presets.destroy', ['preset' => '__PRESET__']) }}">
    <input type="hidden" data-required-collection=".route-user" data-required-label="ผู้พิจารณาในเส้นทางการส่งเอกสาร" data-validation-target=".route-selector">
    <div class="card-header bg-white border-0 pt-4 px-4">
        <h6 class="fw-bold mb-1"><i class="fas fa-route text-primary me-2"></i>เส้นทางการส่งเอกสาร</h6>
        <p class="text-muted small mb-0">เลือกผู้ตรวจ/ผู้พิจารณาตามลำดับ ระบบจะเพิ่มผู้ลงนามหนังสือและล็อกไว้เป็นขั้นตอนสุดท้ายให้อัตโนมัติ</p>
    </div>
    <div class="card-body px-4">
        <div class="rounded-3 border bg-light p-3 mb-3">
            <label class="form-label small fw-bold mb-2" for="route-preset-selector">
                <i class="fas fa-star text-warning me-1"></i>เส้นทางประจำ
            </label>
            <div class="d-flex gap-2 flex-wrap">
                <select id="route-preset-selector" class="form-select form-select-sm route-preset-select flex-grow-1" style="min-width:220px;" aria-label="เลือกเส้นทางประจำ">
                    <option value="">-- เลือกเส้นทางที่บันทึกไว้ --</option>
                </select>
                <button type="button" class="btn btn-primary btn-sm apply-route-preset" disabled>
                    <i class="fas fa-bolt me-1"></i>ใช้เส้นทางนี้
                </button>
                <button type="button" class="btn btn-outline-success btn-sm save-route-preset">
                    <i class="fas fa-bookmark me-1"></i>บันทึกชุดปัจจุบัน
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm delete-route-preset" disabled title="ลบเส้นทางประจำ">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
            <div class="route-preset-status small mt-2 d-none" role="status" aria-live="polite"></div>
        </div>
        <div class="route-rows"></div>
        <button type="button" class="btn btn-outline-primary btn-sm mt-2 add-route">
            <i class="fas fa-plus me-1"></i> เพิ่มผู้พิจารณา
        </button>
        @error('routing_users')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
        @error('routing_users.*')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
        <template class="route-row-template">
            <div class="route-row d-flex gap-2 align-items-center mb-2 flex-wrap flex-md-nowrap p-2 rounded-3 bg-light border">
                <span class="badge bg-primary route-number" style="min-width:2rem">1</span>
                <select name="routing_users[]" class="form-select route-user flex-grow-1" style="min-width: 220px;" required
                        data-required-label="ผู้พิจารณาในเส้นทางการส่งเอกสาร">
                    <option value="">-- เลือกผู้พิจารณา --</option>
                    @foreach($routeUsersByDepartment as $department => $departmentUsers)
                        <optgroup label="{{ $department }}">
                            @foreach($departmentUsers->sortBy(fn ($routeUser) => ($routeUser->position ?: 'zzz').'|'.$routeUser->name) as $routeUser)
                                <option value="{{ $routeUser->id }}">
                                    {{ $routeUser->position ?: 'ไม่ระบุตำแหน่ง' }} — {{ $routeUser->name }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <div class="btn-group route-actions ms-auto" role="group" aria-label="จัดลำดับเส้นทาง">
                    <button type="button" class="btn btn-white border move-up" title="เลื่อนขึ้น"><i class="fas fa-arrow-up"></i></button>
                    <button type="button" class="btn btn-white border move-down" title="เลื่อนลง"><i class="fas fa-arrow-down"></i></button>
                    <button type="button" class="btn btn-outline-danger remove-route" title="ลบ"><i class="fas fa-times"></i></button>
                </div>
            </div>
        </template>
    </div>
</div>

{{-- ใช้ Bootstrap modal แทน browser prompt เพื่อให้หน้าตาและการใช้งานสอดคล้องกับระบบ --}}
<div class="modal fade route-preset-name-modal" tabindex="-1" aria-labelledby="route-preset-name-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg overflow-hidden" style="border-radius: 18px;">
            <div class="modal-header border-0 bg-primary bg-opacity-10 px-4 pt-4 pb-3">
                <div>
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary text-white mb-2" style="width: 42px; height: 42px;">
                        <i class="fas fa-bookmark"></i>
                    </div>
                    <h5 class="modal-title fw-bold text-dark mb-1" id="route-preset-name-modal-title">บันทึกเส้นทางประจำ</h5>
                    <p class="text-muted small mb-0">ตั้งชื่อเพื่อเรียกใช้เส้นทางนี้ได้สะดวกภายหลัง</p>
                </div>
                <button type="button" class="btn-close align-self-start" data-bs-dismiss="modal" aria-label="ปิด"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <label class="form-label fw-semibold" for="route-preset-name-input">ชื่อเส้นทาง <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-lg route-preset-name-input" id="route-preset-name-input"
                       placeholder="เช่น เสนอปลัดและนายก" maxlength="80" autocomplete="off">
                <div class="form-text mt-2"><i class="fas fa-lightbulb text-warning me-1"></i>เลือกชื่อที่สื่อถึงลำดับผู้พิจารณา</div>
                <div class="invalid-feedback route-preset-name-error">กรุณาระบุชื่อเส้นทางประจำ</div>
            </div>
            <div class="modal-footer border-0 bg-light px-4 py-3">
                <button type="button" class="btn btn-light border rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-primary rounded-pill px-4 route-preset-name-submit">
                    <i class="fas fa-bookmark me-1"></i>บันทึกเส้นทาง
                </button>
            </div>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-route-selector]').forEach(container => {
        const rows = container.querySelector('.route-rows');
        const template = container.querySelector('.route-row-template');
        const initial = @json(array_values($selectedRoutes));
        const presetSelect = container.querySelector('.route-preset-select');
        const applyPresetButton = container.querySelector('.apply-route-preset');
        const savePresetButton = container.querySelector('.save-route-preset');
        const deletePresetButton = container.querySelector('.delete-route-preset');
        const presetStatus = container.querySelector('.route-preset-status');
        const nameModalElement = container.nextElementSibling;
        const nameInput = nameModalElement.querySelector('.route-preset-name-input');
        const nameSubmitButton = nameModalElement.querySelector('.route-preset-name-submit');
        const nameError = nameModalElement.querySelector('.route-preset-name-error');
        const nameModal = bootstrap.Modal.getOrCreateInstance(nameModalElement);
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const presetList = JSON.parse(container.dataset.presets || '[]');
        const presets = new Map(presetList.map(preset => [String(preset.id), preset]));
        const form = container.closest('form');
        const signerSelect = form?.querySelector('#signer');
        const signerUserIdInput = form?.querySelector('#signer_user_id');

        const showPresetStatus = (message, isError = false) => {
            presetStatus.textContent = message;
            presetStatus.classList.remove('d-none', 'text-success', 'text-danger');
            presetStatus.classList.add(isError ? 'text-danger' : 'text-success');
        };

        const renderPresetOptions = (selectedId = '') => {
            presetSelect.replaceChildren(new Option('-- เลือกเส้นทางที่บันทึกไว้ --', ''));
            [...presets.values()]
                .sort((a, b) => a.name.localeCompare(b.name, 'th'))
                .forEach(preset => presetSelect.add(new Option(
                    `${preset.name} (${preset.route_user_ids.length} คน)`,
                    String(preset.id)
                )));
            presetSelect.value = String(selectedId);
            const hasSelection = presets.has(presetSelect.value);
            applyPresetButton.disabled = !hasSelection;
            deletePresetButton.disabled = !hasSelection;
        };

        const updateAvailableUsers = () => {
            const selects = [...rows.querySelectorAll('.route-user')];
            selects.forEach(currentSelect => {
                const selectedElsewhere = new Set(
                    selects
                        .filter(select => select !== currentSelect)
                        .map(select => select.value)
                        .filter(Boolean)
                );

                currentSelect.querySelectorAll('option[value]').forEach(option => {
                    if (!option.value) return;
                    const unavailable = selectedElsewhere.has(option.value);
                    option.disabled = unavailable;
                    option.hidden = unavailable;
                });

                currentSelect.querySelectorAll('optgroup').forEach(group => {
                    group.hidden = [...group.querySelectorAll('option')].every(option => option.hidden);
                });
            });
        };

        const renumber = () => rows.querySelectorAll('.route-row').forEach((row, index) => {
            row.querySelector('.route-number').textContent = index + 1;
            const isSigner = row.dataset.routeRole === 'signer';
            row.querySelector('.move-up').disabled = isSigner || index === 0;
            row.querySelector('.move-down').disabled = isSigner
                || index === rows.children.length - 1
                || row.nextElementSibling?.dataset.routeRole === 'signer';
            row.querySelector('.remove-route').disabled = isSigner;
            row.classList.toggle('border-success', isSigner);
            row.querySelector('.route-number').classList.toggle('bg-success', isSigner);
            row.querySelector('.route-user').style.pointerEvents = isSigner ? 'none' : '';
            row.querySelector('.route-user').setAttribute('aria-label', isSigner ? 'ผู้ลงนามหนังสือ (ล็อกเป็นขั้นตอนสุดท้าย)' : 'ผู้พิจารณา');
        });

        const addRow = (value = '') => {
            const row = template.content.firstElementChild.cloneNode(true);
            const select = row.querySelector('.route-user');
            select.value = String(value);
            select.addEventListener('change', () => {
                const duplicate = [...rows.querySelectorAll('.route-user')]
                    .some(other => other !== select && other.value && other.value === select.value);
                if (duplicate) {
                    select.value = '';
                    alert('ผู้พิจารณาคนนี้ถูกเลือกไว้ในลำดับอื่นแล้ว');
                }
                updateAvailableUsers();
            });
            row.querySelector('.remove-route').addEventListener('click', () => {
                row.remove();
                renumber();
                updateAvailableUsers();
            });
            row.querySelector('.move-up').addEventListener('click', () => {
                if (row.previousElementSibling) rows.insertBefore(row, row.previousElementSibling);
                renumber();
            });
            row.querySelector('.move-down').addEventListener('click', () => {
                if (row.nextElementSibling) rows.insertBefore(row.nextElementSibling, row);
                renumber();
            });
            rows.appendChild(row);
            renumber();
            updateAvailableUsers();
        };

        const syncSignerRoute = () => {
            if (!signerSelect) return;

            const signerId = signerSelect.selectedOptions[0]?.dataset.userId || '';
            if (signerUserIdInput) signerUserIdInput.value = signerId;

            if (signerId) {
                rows.querySelectorAll('.route-row').forEach(row => {
                    if (!row.querySelector('.route-user').value) row.remove();
                });
            }

            rows.querySelectorAll('[data-route-role="signer"]').forEach(row => {
                if (row.querySelector('.route-user').value !== signerId) row.remove();
            });
            if (!signerId) {
                renumber();
                updateAvailableUsers();
                return;
            }

            let signerRow = [...rows.querySelectorAll('.route-row')]
                .find(row => row.querySelector('.route-user').value === signerId);
            if (!signerRow) {
                addRow(signerId);
                signerRow = rows.lastElementChild;
            }
            signerRow.dataset.routeRole = 'signer';
            rows.appendChild(signerRow);
            renumber();
            updateAvailableUsers();
        };

        container.querySelector('.add-route').addEventListener('click', () => addRow());
        (initial.length ? initial : ['']).forEach(addRow);
        signerSelect?.addEventListener('change', syncSignerRoute);
        syncSignerRoute();
        renderPresetOptions();

        presetSelect.addEventListener('change', () => {
            const hasSelection = presets.has(presetSelect.value);
            applyPresetButton.disabled = !hasSelection;
            deletePresetButton.disabled = !hasSelection;
        });

        applyPresetButton.addEventListener('click', () => {
            const preset = presets.get(presetSelect.value);
            if (!preset) return;

            const availableUserIds = new Set(
                [...template.content.querySelectorAll('.route-user option[value]')]
                    .map(option => option.value)
                    .filter(Boolean)
            );
            const validUserIds = preset.route_user_ids.map(String).filter(id => availableUserIds.has(id));
            if (!validUserIds.length) {
                showPresetStatus('ผู้พิจารณาในเส้นทางนี้ไม่อยู่ในรายชื่อผู้ใช้งานปัจจุบัน', true);
                return;
            }

            rows.replaceChildren();
            validUserIds.forEach(addRow);
            syncSignerRoute();
            const skipped = preset.route_user_ids.length - validUserIds.length;
            showPresetStatus(skipped
                ? `ใช้เส้นทาง “${preset.name}” แล้ว (ข้ามผู้ใช้ที่ไม่พร้อมใช้งาน ${skipped} คน)`
                : `ใช้เส้นทาง “${preset.name}” แล้ว`);
        });

        savePresetButton.addEventListener('click', async () => {
            const routeUserIds = [...rows.querySelectorAll('.route-user')]
                .filter(select => select.closest('.route-row')?.dataset.routeRole !== 'signer')
                .map(select => select.value)
                .filter(Boolean);
            if (!routeUserIds.length) {
                showPresetStatus('กรุณาเลือกผู้พิจารณาอย่างน้อย 1 คนก่อนบันทึก', true);
                return;
            }

            const suggestedName = presets.get(presetSelect.value)?.name || '';
            nameInput.value = suggestedName;
            nameInput.classList.remove('is-invalid');
            nameError.textContent = 'กรุณาระบุชื่อเส้นทางประจำ';
            nameModal.show();
        });

        nameModalElement.addEventListener('shown.bs.modal', () => {
            nameInput.focus();
            nameInput.select();
        });

        nameInput.addEventListener('input', () => nameInput.classList.remove('is-invalid'));
        nameInput.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                event.preventDefault();
                nameSubmitButton.click();
            }
        });

        nameSubmitButton.addEventListener('click', async () => {
            const name = nameInput.value.trim();
            if (!name) {
                nameInput.classList.add('is-invalid');
                nameInput.focus();
                return;
            }

            const routeUserIds = [...rows.querySelectorAll('.route-user')]
                .filter(select => select.closest('.route-row')?.dataset.routeRole !== 'signer')
                .map(select => select.value)
                .filter(Boolean);

            savePresetButton.disabled = true;
            nameSubmitButton.disabled = true;
            try {
                const response = await fetch(container.dataset.storeUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ name, route_user_ids: routeUserIds }),
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.errors?.name?.[0] || result.message || 'บันทึกเส้นทางประจำไม่สำเร็จ');

                presets.set(String(result.preset.id), result.preset);
                renderPresetOptions(result.preset.id);
                showPresetStatus(result.message);
                nameModal.hide();
            } catch (error) {
                const message = error.message || 'บันทึกเส้นทางประจำไม่สำเร็จ';
                nameError.textContent = message;
                nameInput.classList.add('is-invalid');
            } finally {
                savePresetButton.disabled = false;
                nameSubmitButton.disabled = false;
            }
        });

        deletePresetButton.addEventListener('click', async () => {
            const preset = presets.get(presetSelect.value);
            if (!preset || !window.confirm(`ลบเส้นทางประจำ “${preset.name}” ใช่หรือไม่`)) return;

            deletePresetButton.disabled = true;
            try {
                const deleteUrl = container.dataset.deleteUrlTemplate.replace('__PRESET__', preset.id);
                const response = await fetch(deleteUrl, {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'ลบเส้นทางประจำไม่สำเร็จ');

                presets.delete(String(preset.id));
                renderPresetOptions();
                showPresetStatus(result.message);
            } catch (error) {
                showPresetStatus(error.message || 'ลบเส้นทางประจำไม่สำเร็จ', true);
                deletePresetButton.disabled = false;
            }
        });

        container.closest('form')?.addEventListener('submit', event => {
            const values = [...rows.querySelectorAll('.route-user')].map(select => select.value).filter(Boolean);
            if (new Set(values).size !== values.length) {
                event.preventDefault();
                alert('ไม่สามารถเลือกผู้พิจารณาคนเดิมซ้ำในเส้นทางเดียวกันได้');
            }
        });
    });
});
</script>
@endpush
@endonce
