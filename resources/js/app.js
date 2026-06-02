import './bootstrap';
import * as bootstrap from 'bootstrap';
import.meta.glob([
    '../img/**'
])

window.bootstrap = bootstrap;

function setupConfirmModal() {
    const modal = document.querySelector('[data-confirm-modal]');

    if (!modal) {
        return;
    }

    let pendingForm = null;
    const message = modal.querySelector('[data-confirm-message]');
    const acceptButton = modal.querySelector('[data-confirm-accept]');
    const cancelButton = modal.querySelector('[data-confirm-cancel]');

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form[data-confirm]');

        if (!form || form.dataset.confirmed === 'true') {
            return;
        }

        event.preventDefault();
        pendingForm = form;
        modal.querySelector('h2').textContent = form.dataset.confirm;
        message.textContent = form.dataset.confirmDetail || 'Questa operazione non puo essere annullata.';
        modal.classList.remove('d-none');
        requestAnimationFrame(() => modal.classList.add('is-visible'));
    });

    cancelButton.addEventListener('click', () => {
        modal.classList.remove('is-visible');
        pendingForm = null;
        setTimeout(() => modal.classList.add('d-none'), 180);
    });

    acceptButton.addEventListener('click', () => {
        if (!pendingForm) {
            return;
        }

        pendingForm.dataset.confirmed = 'true';
        pendingForm.requestSubmit();
    });
}

setupConfirmModal();
