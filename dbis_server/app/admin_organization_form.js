import Pickr from '@simonwep/pickr';
import './scss/admin_base.scss';
import '@fortawesome/fontawesome-free/js/all.js';
import {validateForm} from './js/modules/validation';

let fileInput = null;
let fileNameInput = null;
let lblFileName = null;
let iconDeleteBtn = null;
let iconPreview = null;
let iconPreviewNotification = null;
let btnAddExternalIdentifier = null;
let btnAddLink = null;
let externalOrgIdsContainer = null;
let externalOrgIdsItemTemplate = null;
let linksContainer = null;
let linksTemplate = null;

function setPreview(target, filename) {
    lblFileName.textContent = filename;
    iconPreview.src = target;
    fileNameInput.value = filename;

    iconPreview.style.display = target ? 'block' : 'none';
    iconPreviewNotification.style.display = target ? 'none' : 'block';
}

function addExternalIdentifier()
{
    if (externalOrgIdsItemTemplate) {
        const clone = externalOrgIdsItemTemplate.cloneNode(true);
        const clonedSelector = clone.querySelector("select");
        const clonedInput = clone.querySelector("input");
        clone.style.display = "flex";
        externalOrgIdsContainer.appendChild(clone);
    }
}

function setupColorpicker() {
    const inputElement = document.querySelector('.pickr');
    // const inputColor = document.querySelector('input[name="color"]');

    const pickr = Pickr.create({
        el: inputElement,
        useAsButton: true,
        theme: 'nano', // or 'monolith', or 'nano'
        lockOpacity: true,
        swatches: null,
        components: {
            // Main components
            preview: true,
            opacity: false,
            hue: true,
            // Input / output Options
            interaction: {
                hex: true,
                rgba: false,
                hsla: false,
                hsva: false,
                cmyk: false,
                input: true,
                clear: true,
                save: true
            }
        },
        i18n: {
            // Strings visible in the UI
            'ui:dialog': 'color picker dialog',
            'btn:toggle': 'toggle color picker dialog',
            'btn:swatch': 'color swatch',
            'btn:last-color': 'use previous color',
            'btn:save': 'Save',
            'btn:cancel': 'Cancel',
            'btn:clear': 'Clear',
            // Strings used for aria-labels
            'aria:btn:save': 'save and close',
            'aria:btn:cancel': 'cancel and close',
            'aria:btn:clear': 'clear and close',
            'aria:input': 'color input field',
            'aria:palette': 'color selection area',
            'aria:hue': 'hue selection slider',
            'aria:opacity': 'selection slider'
        }
    }).on('init', pickr => {
        pickr.setColor(inputElement.value);
        inputElement.style.backgroundColor = inputElement.value;
    }).on('save', color => {
        if (color) {
            inputElement.value = color.toHEXA().toString(0);
        } else {
            inputElement.value = "";
            // inputColor.value = "";
        }
        
        inputElement.style.backgroundColor = inputElement.value;
        pickr.hide();
    });
}

function initialize() {
    validateForm();
    addExternalIdentifier();
    setupColorpicker();
}

function addLink() {
    const clone = linksTemplate.cloneNode(true);
    const btnDelete = clone.querySelector("button.delete");
    clone.style.display = "flex";
    linksContainer.appendChild(clone);

    btnDelete.addEventListener('click', function() {
        clone.remove();
    });
}

document.addEventListener("DOMContentLoaded", function (event) {
    fileInput = document.querySelector('#organization-icon-upload input[type=file]');
    fileNameInput = document.querySelector('#organization-icon-filepath');
    lblFileName = document.querySelector('#organization-icon-upload .file-name');
    iconDeleteBtn = document.querySelector('#icon-delete-btn');
    iconPreview = document.getElementById("organization-icon-preview");
    iconPreviewNotification = document.getElementById("organization-icon-preview_notification");
    btnAddExternalIdentifier = document.getElementById("btn_add_organization_identifier");
    btnAddLink = document.getElementById("btn_add_link");
    externalOrgIdsContainer = document.getElementById("external-org-ids");
    externalOrgIdsItemTemplate = document.getElementById("external_ids_template");
    linksContainer = document.getElementById("links");
    linksTemplate = document.getElementById("links_template");

    // taken form bulma docs = https://bulma.io/documentation/form/file/
    fileInput.onchange = () => {
        if (fileInput.files.length > 0) {
            const fileName = fileInput.files[0].name;
            // Code courtesy to s.o.-user santosh singh,
            // https://stackoverflow.com/questions/18457340/how-to-preview-selected-image-in-input-type-file-in-popup-using-jquery
            let oFReader = new FileReader();
            oFReader.readAsDataURL(fileInput.files[0]);

            oFReader.onload = function (oFREvent) {
                setPreview(oFREvent.target.result, fileName);
            };
        }
    };

    iconDeleteBtn.onclick = (evt) => {
        evt.preventDefault();
        setPreview(null, null);
    };

    if (btnAddExternalIdentifier) {
        btnAddExternalIdentifier.onclick = (evt) => {
            evt.preventDefault();
            addExternalIdentifier();
        };
    }

    if (btnAddLink) {
        btnAddLink.onclick = (evt) => {
            evt.preventDefault();
            addLink();
        };
    }

    const linkEntries = document.querySelectorAll('.link-entry');
    // Set up deletion of authors that exist already
    linkEntries.forEach(function(entry) {
        // Check needed, because one field is available by default (without delete cross)
        if (entry.querySelector('button.delete') !== null) {
            entry.querySelector('button.delete').addEventListener('click', function() {
                entry.remove();
                // entry.querySelector(`input[name='link[]']`).value = "";
                // entry.classList.add('is-hidden');
            });
        }
    });

    initialize();
});
