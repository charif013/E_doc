@once
<style>
    .edoc-required-invalid {
        border-color: #dc3545 !important;
        box-shadow: 0 0 0 .2rem rgba(220, 53, 69, .12) !important;
    }

    label.edoc-required-invalid,
    .leave-type-card.edoc-required-invalid,
    #dropzone.edoc-required-invalid {
        color: #b02a37 !important;
        background-color: #fff5f5 !important;
    }
</style>
<script>
window.EdocRequiredValidation = (() => {
    const invalidClass = 'edoc-required-invalid';

    const escapeHtml = value => {
        const node = document.createElement('div');
        node.textContent = value;
        return node.innerHTML;
    };

    const valueMissing = field => {
        if (field.disabled) return false;
        if (field.type === 'radio') {
            return !field.form.querySelectorAll(`[name="${CSS.escape(field.name)}"]:checked`).length;
        }
        if (field.type === 'checkbox') return !field.checked;
        if (field.type === 'file') return !field.files.length;
        return !String(field.value ?? '').trim();
    };

    const labelFor = field => {
        if (field.dataset.requiredLabel) return field.dataset.requiredLabel;
        const explicit = field.id ? document.querySelector(`label[for="${CSS.escape(field.id)}"]`) : null;
        const nearby = explicit || field.closest('.field-group, .col-12, .col-md-4, .col-md-6')?.querySelector('label');
        return (nearby?.textContent || field.name || 'ข้อมูลที่จำเป็น')
            .replace(/\*/g, '')
            .replace(/\s+/g, ' ')
            .trim();
    };

    const visualTarget = field => {
        if (field.dataset.validationTarget) return document.querySelector(field.dataset.validationTarget) || field;
        if (field.type === 'radio') return field.form.querySelector(`label[for="${CSS.escape(field.id)}"]`) || field;
        if (field.type === 'file' && field.id === 'file') return document.getElementById('dropzone') || field;
        if (field.classList.contains('select2-hidden-accessible')) return field.nextElementSibling || field;
        return field._flatpickr?.altInput || field;
    };

    const clearField = field => {
        visualTarget(field)?.classList.remove(invalidClass);
        if (field.type === 'radio') {
            field.form.querySelectorAll(`[name="${CSS.escape(field.name)}"]`).forEach(item => {
                const label = item.id ? field.form.querySelector(`label[for="${CSS.escape(item.id)}"]`) : null;
                label?.classList.remove(invalidClass);
            });
        }
    };

    const invalidFields = (form, scope = form) => {
        const fields = [...scope.querySelectorAll('[required], [data-required-any], [data-required-collection]')];
        const seenRadioNames = new Set();
        const seenAnyGroups = new Set();
        const invalid = [];

        fields.forEach(field => {
            clearField(field);

            if (field.dataset.requiredCollection) {
                const container = field.closest('[data-route-selector]') || form;
                const members = [...container.querySelectorAll(field.dataset.requiredCollection)];
                if (!members.length || members.every(valueMissing)) invalid.push(field);
                return;
            }

            const anyGroup = field.dataset.requiredAny;
            if (anyGroup) {
                if (seenAnyGroups.has(anyGroup)) return;
                seenAnyGroups.add(anyGroup);
                const members = [...form.querySelectorAll(`[data-required-any="${CSS.escape(anyGroup)}"]`)];
                if (members.every(valueMissing)) invalid.push(field);
                return;
            }

            if (field.type === 'radio') {
                if (seenRadioNames.has(field.name)) return;
                seenRadioNames.add(field.name);
            }

            if (valueMissing(field) || (field.type !== 'hidden' && !field.checkValidity())) invalid.push(field);
        });

        return invalid;
    };

    const focusField = field => {
        const target = visualTarget(field);
        target?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (field._flatpickr?.altInput) field._flatpickr.altInput.focus({ preventScroll: true });
        else if (field.classList.contains('select2-hidden-accessible') && window.jQuery) jQuery(field).select2('open');
        else if (field.type !== 'hidden' && field.type !== 'file' && field.type !== 'radio') field.focus({ preventScroll: true });
        else target?.focus?.({ preventScroll: true });
    };

    const notify = fields => {
        fields.forEach(field => visualTarget(field)?.classList.add(invalidClass));
        const labels = [...new Set(fields.map(labelFor))];
        const html = `<p class="mb-2">กรุณากรอกข้อมูลในส่วนที่จำเป็นต่อไปนี้</p><ul class="text-start mb-0">${labels.map(label => `<li>${escapeHtml(label)}</li>`).join('')}</ul>`;
        const afterClose = () => focusField(fields[0]);

        if (window.Swal) {
            Swal.fire({
                icon: 'warning',
                title: 'ยังกรอกข้อมูลไม่ครบ',
                html,
                confirmButtonText: 'กลับไปกรอกข้อมูล',
                confirmButtonColor: '#0284c7',
            }).then(afterClose);
        } else {
            window.alert(`ยังกรอกข้อมูลไม่ครบ:\n- ${labels.join('\n- ')}`);
            afterClose();
        }
    };

    const validate = (formOrScope, explicitScope = null) => {
        const form = formOrScope.matches?.('form') ? formOrScope : formOrScope.closest('form');
        const scope = explicitScope || formOrScope;
        const invalid = invalidFields(form, scope);
        if (!invalid.length) return true;
        notify(invalid);
        return false;
    };

    const clearChangedField = field => {
        if (field.matches('[required], [data-required-any], [data-required-collection]')) clearField(field);
        const collectionMarker = field.closest('[data-route-selector]')?.querySelector('[data-required-collection]');
        if (collectionMarker) clearField(collectionMarker);
    };

    document.addEventListener('input', event => clearChangedField(event.target));
    document.addEventListener('change', event => clearChangedField(event.target));

    return { validate, invalidFields, notify };
})();
</script>
@endonce
