import './scss/admin_base.scss';
import '@fortawesome/fontawesome-free/js/all.js';
import {validateForm} from './js/modules/validation';

document.addEventListener("DOMContentLoaded", function (event) {
    const confirmInput = document.querySelector('#confirm_ubrid');
    const deleteBtn = document.querySelector('#delete-organization-button');
    const deleteModal = document.querySelector('#confirm-delete-modal');
    const deleteOrgFormCloseBtn = document.querySelector('#confirm-delete-modal button.delete');
    const deleteOrgFormCancelBtn = document.querySelector('#confirm-delete-modal button.cancel');
    const deleteOrgFormSubmitBtn = document.querySelector('#confirm-delete-modal button.submit');
    const deleteOrgFormBtn = document.querySelector('#confirm-delete-modal button.delete');
    const deleteOrgForm = document.querySelector('#delete-org-form');
    // taken form bulma docs = https://bulma.io/documentation/form/file/
    confirmInput.onkeyup = () => {
        const expected = confirmInput.dataset.expectedValue;
        deleteBtn.disabled = (confirmInput.value !== expected);
    };
    
    function toggleModal(state) {
        if(state) {
            deleteModal.style.display = "flex";
        } else {
            deleteModal.style.display = "none";        
        }
    };

    deleteOrgForm.onsubmit = (event) => {
        toggleModal(true);
        event.preventDefault();
    };

    deleteOrgFormCloseBtn.onclick = (event) => {
        toggleModal(false);
    };

    deleteOrgFormCancelBtn.onclick = (event) => {
        toggleModal(false);
    };

    deleteOrgFormSubmitBtn.onclick = (event) => {
        deleteOrgForm.submit();
    };

    const fileInput = document.querySelector('#organization-icon-upload input[type=file]');
    // taken form bulma docs = https://bulma.io/documentation/form/file/
    fileInput.onchange = () => {
        if (fileInput.files.length > 0) {
            const fileName = document.querySelector('#organization-icon-upload .file-name');
            fileName.textContent = fileInput.files[0].name;
        }
    };
    
    validateForm();
});