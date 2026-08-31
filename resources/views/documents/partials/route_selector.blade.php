@php
    $selectedRoutes = old('routing_users', []);
    $routeUsersByDepartment = $users
        ->groupBy(fn ($routeUser) => $routeUser->department ?: 'ไม่ระบุสำนัก/กอง')
        ->sortKeys();
@endphp

<div class="card border-0 shadow-sm mb-4 route-selector" data-route-selector>
    <div class="card-header bg-white border-0 pt-4 px-4">
        <h6 class="fw-bold mb-1"><i class="fas fa-route text-primary me-2"></i>เส้นทางการส่งเอกสาร</h6>
        <p class="text-muted small mb-0">เลือกผู้พิจารณาตามลำดับ ระบบจะส่งเอกสารให้ทีละคนจากบนลงล่าง</p>
    </div>
    <div class="card-body px-4">
        <div class="route-rows"></div>
        <button type="button" class="btn btn-outline-primary btn-sm mt-2 add-route">
            <i class="fas fa-plus me-1"></i> เพิ่มผู้พิจารณา
        </button>
        @error('routing_users')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
        @error('routing_users.*')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
        <template class="route-row-template">
            <div class="route-row d-flex gap-2 align-items-center mb-2 flex-wrap flex-md-nowrap p-2 rounded-3 bg-light border">
                <span class="badge bg-primary route-number" style="min-width:2rem">1</span>
                <select name="routing_users[]" class="form-select route-user flex-grow-1" style="min-width: 220px;" required>
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

@once
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-route-selector]').forEach(container => {
        const rows = container.querySelector('.route-rows');
        const template = container.querySelector('.route-row-template');
        const initial = @json(array_values($selectedRoutes));

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
            row.querySelector('.move-up').disabled = index === 0;
            row.querySelector('.move-down').disabled = index === rows.children.length - 1;
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

        container.querySelector('.add-route').addEventListener('click', () => addRow());
        (initial.length ? initial : ['']).forEach(addRow);

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
