import '@fortawesome/fontawesome-free/js/all.js'
// import $ from "cash-dom"
import $ from 'jquery';
import 'chosen-js/chosen.jquery.js';
import './scss/admin_base.scss'
import './scss/pages/admin_create_database.scss'
import './admin_base'
import {TranslatableText} from './js/modules/translatable_text.js';

import {addRequiredValidator} from './js/modules/validation'
import {AutocompleteSearch} from "./js/modules/autocomplete-search";


class CreateDatabase {
    constructor() {
        this.keywordListDe = document.getElementsByClassName('keyword-list-de')[0];
        this.keywordListEn = document.getElementsByClassName('keyword-list-en')[0];
        this.keywordListId = document.getElementsByClassName('keyword-list-de')[0];

        this.authorListDe = document.getElementsByClassName('author-list-de')[0];
        this.authorListEn = document.getElementsByClassName('author-list-en')[0];
    }

    initEvents() {
        $('#subjects_local').chosen({width: "100%"});
        $('#subjects_global').chosen({width: "100%"});

        $('#type_local').chosen({width: "100%"});
        $('#type_global').chosen({width: "100%"});
        
        $('#country-local').chosen({width: "100%"});
        $('#country-global').chosen({width: "100%"});

        this.initKeywords();
        this.initAuthors();
        this.initAltTitle();
        this.initLocalNote();
        this.initShortDescription();
        this.initTranslations();
        this.initApiUrl();
        this.initLocalizationTags();
        this.initCopyButton();
    }


    initValidation() {
        for (let field of document.getElementsByClassName('validate-required')) {
            addRequiredValidator(field);
        }
    }

    initKeywords() {
        const buttonAddKeywordGlobal = document.querySelector('#btn_add_database_keyword_global');
        const keywordEntriesGlobal = document.querySelectorAll('.field-keywords.global .keyword-entry');
        const buttonAddKeywordLocal = document.querySelector('#btn_add_database_keyword_local');
        const keywordEntriesLocal = document.querySelectorAll('.field-keywords.local .keyword-entry');

        // GLOBAL
        // Set up add keyword button
        buttonAddKeywordGlobal.addEventListener('click', event => {
            event.preventDefault();
            const entries = Array.from(document.querySelectorAll(".field-keywords.global .keyword-entry"));
            const template = entries[entries.length-1];
            const newNode = template.cloneNode(true);
            newNode.querySelector(`input[name='keyword_global_id[]']`).value = "";
            newNode.querySelector(`input[name='keyword_global_de[]']`).value = "";
            newNode.querySelector(`input[name='keyword_global_en[]']`).value = "";
            newNode.classList.remove('is-hidden');
            newNode.querySelector('btn.delete').addEventListener('click', function() {
                newNode.querySelector(`input[name='keyword_global_id[]']`).value = "";
                newNode.querySelector(`input[name='keyword_global_de[]']`).value = "";
                newNode.querySelector(`input[name='keyword_global_en[]']`).value = "";
                newNode.classList.add('is-hidden');
            });

            template.parentNode.appendChild(newNode);

            const root = newNode.querySelector(".keyword-typeahead--de");
            this._initTypeahead(root);

            newNode.querySelector(`input[name='keyword_global_de[]']`).focus();
        });

        // Set up deletion of keywords that exist already
        keywordEntriesGlobal.forEach((entry) => {
            // Check needed, because one field is available by default (without delete cross)
            if (entry.querySelector('btn.delete') !== null) {
                entry.querySelector('btn.delete').addEventListener('click', function() {
                    entry.querySelector(`input[name='keyword_global_id[]']`).value = "";
                    entry.querySelector(`input[name='keyword_global_de[]']`).value = "";
                    entry.querySelector(`input[name='keyword_global_en[]']`).value = "";
                    entry.classList.add('is-hidden');
                });
            }
            const root = entry.querySelector(".keyword-typeahead--de");
            this._initTypeahead(root);
        });

        // Set up add keyword button
        buttonAddKeywordLocal.addEventListener('click', event => {
            event.preventDefault();
            const entries = Array.from(document.querySelectorAll(".field-keywords.local .keyword-entry"));
            const template = entries[entries.length-1];
            const newNode = template.cloneNode(true);
            newNode.querySelector(`input[name='keyword_local_id[]']`).value = "";
            newNode.querySelector(`input[name='keyword_local_de[]']`).value = "";
            newNode.querySelector(`input[name='keyword_local_en[]']`).value = "";
            newNode.classList.remove('is-hidden');
            newNode.querySelector('btn.delete').addEventListener('click', function() {
                newNode.querySelector(`input[name='keyword_local_id[]']`).value = "";
                newNode.querySelector(`input[name='keyword_local_de[]']`).value = "";
                newNode.querySelector(`input[name='keyword_local_en[]']`).value = "";
                newNode.classList.add('is-hidden');
            });

            template.parentNode.appendChild(newNode);

            const root = newNode.querySelector(".keyword-typeahead--de");
            this._initTypeahead(root);

            newNode.querySelector(`input[name='keyword_local_de[]']`).focus();
        });

        // Set up deletion of keywords that exist already
        keywordEntriesLocal.forEach((entry) => {
            // Check needed, because one field is available by default (without delete cross)
            if (entry.querySelector('btn.delete') !== null) {
                entry.querySelector('btn.delete').addEventListener('click', function() {
                    entry.querySelector(`input[name='keyword_local_id[]']`).value = "";
                    entry.querySelector(`input[name='keyword_local_de[]']`).value = "";
                    entry.querySelector(`input[name='keyword_local_en[]']`).value = "";
                    entry.classList.add('is-hidden');
                });
            }

            const root = entry.querySelector(".keyword-typeahead--de");
            this._initTypeahead(root);
        });
    }

    _initTypeahead(root) {
        // Insert const root = document.querySelector(".keyword-typeahead--de");
        const template = root.querySelector("#template-dropdown-entry");
        const autocompleteSearch = new AutocompleteSearch(root, template);

        // Initialize typeahead
        autocompleteSearch.ontypeahead = (q) => {
            // Here the keyword system and the external id need to be reset
            root.querySelector(`input.keyword_system`).value = "";
            root.querySelector(`input.external_id`).value = "";

            // Populate gnd keywords
            this._getGNDKeywords(q)
                .then(data => autocompleteSearch.setDropdownList(data));
        }

        autocompleteSearch.onselect = (event) => {
            const button = event.target;
            const id = parseInt(button.value);
            const label = button.innerHTML;

            // Individual case when searching for gnd keywords
            if (button.dataset.keyword_system) {
                root.querySelector(`input.keyword_system`).value = button.dataset.keyword_system;
            }
            
            if (button.dataset.external_id) {
                root.querySelector(`input.external_id`).value = button.dataset.external_id;
            }
            // reset the autocomplete field
            autocompleteSearch.reset();

            // But insert value
            autocompleteSearch.search.value = label;
        }
    }

    async _getGNDKeywords(q) {
        const params = {
            q: q,
            format : "json:preferredName,professionOrOccupation"
        }

        const url = `https://lobid.org/gnd/search?q=${params.q}&format=${params.format}`

        const response = await fetch(url);
        const responseJSON = await response.json();

        let pattern = /(\d+\-*(\d|x))/i;

        const data = responseJSON.map((item) => {
            const matches = item.id.match(pattern);
            const gndId = matches[0];

            return {
                'id': gndId,
                'title': item.label,
                'external_id': gndId,
                'category': item.category,
                'link': item.id,
                'keyword_system': "gnd"
            }
        });

        return data
    }

    initAuthors() {
        const buttonAddAuthorGlobal = document.querySelector('#btn_add_database_author_global');
        const authorEntriesGlobal = document.querySelectorAll('.field-author.global .author-entry');
        const buttonAddAuthorLocal = document.querySelector('#btn_add_database_author_local');
        const authorEntriesLocal = document.querySelectorAll('.field-author.local .author-entry');

        // GLOBAL
        // Set up add author button
        buttonAddAuthorGlobal.addEventListener('click', event => {
            event.preventDefault();
            const entries = Array.from(document.querySelectorAll(".field-author.global .author-entry"));
            const template = entries[entries.length-1];
            const newNode = template.cloneNode(true);
            newNode.querySelector(`input[name='author_global[]']`).value = "";
            newNode.classList.remove('is-hidden');
            newNode.querySelector('btn.delete').addEventListener('click', function() {
                //newNode.querySelector(`input[name='author_global_id[]'`).value = ""; // authors are not yet connected with id
                newNode.querySelector(`input[name='author_global[]']`).value = "";
                newNode.classList.add('is-hidden');
            });

            template.parentNode.appendChild(newNode);
            newNode.querySelector(`input[name='author_global[]']`).focus();
        });

        // Set up deletion of authors that exist already
        authorEntriesGlobal.forEach(function(entry) {
            // Check needed, because one field is available by default (without delete cross)
            if (entry.querySelector('btn.delete') !== null) {
                entry.querySelector('btn.delete').addEventListener('click', function() {
                    //entry.querySelector(`input[name='author_global_id[]'`).value = ""; // authors are not yet connected with id
                    entry.querySelector(`input[name='author_global[]']`).value = "";
                    entry.classList.add('is-hidden');
                });
            }
        });

        // LOCAL
        // Set up add author button
        buttonAddAuthorLocal.addEventListener('click', event => {
            event.preventDefault();
            const entries = Array.from(document.querySelectorAll(".field-author.local .author-entry"));
            const template = entries[entries.length-1];
            const newNode = template.cloneNode(true);
            //newNode.querySelector(`input[name='author_local_id[]'`).value = ""; // authors are not yet connected with id
            newNode.querySelector(`input[name='author_local_de[]']`).value = "";
            newNode.classList.remove('is-hidden');
            newNode.querySelector('btn.delete').addEventListener('click', function() {
                //newNode.querySelector(`input[name='author_local_id[]'`).value = ""; // authors are not yet connected with id
                newNode.querySelector(`input[name='author_local_de[]']`).value = "";
                newNode.classList.add('is-hidden');
            });

            template.parentNode.appendChild(newNode);
            newNode.querySelector(`input[name='author_local_de[]']`).focus();
        });

        // Set up deletion of authors that exist already
        authorEntriesLocal.forEach(function(entry) {
            // Check needed, because one field is available by default (without delete cross)
            if (entry.querySelector('btn.delete') !== null) {
                entry.querySelector('btn.delete').addEventListener('click', function() {
                    //entry.querySelector(`input[name='author_local_id[]'`).value = ""; // authors are not yet connected with id
                    entry.querySelector(`input[name='author_local_de[]']`).value = "";
                    entry.classList.add('is-hidden');
                });
            }
        });
    }

    initAltTitle() {
        const buttonAddAltTitleGlobal = document.querySelector('#btn_add_alternative_title_global');
        const altTitleEntriesGlobal = document.querySelectorAll('.field-alt_titles.global .alternative-title-entry');

        // GLOBAL
        // Set up add alternative title button
        buttonAddAltTitleGlobal.addEventListener('click', event => {
            event.preventDefault();
            const entries = Array.from(document.querySelectorAll(".field-alt_titles.global .alternative-title-entry"));
            const template = entries[entries.length-1];
            const newNode = template.cloneNode(true);
            newNode.querySelector(`input[name='alternative_title_global[]']`).value = "";
            newNode.querySelector(`input[name='alternative_title_valid_from_global[]']`).value = "";
            newNode.querySelector(`input[name='alternative_title_valid_to_global[]']`).value = "";

            document.querySelector('#alternativetitle-block #alternativetitle-title').classList.remove('is-hidden');
            document.querySelector('#alternativetitle-block').classList.add('mt-6');
            newNode.classList.remove('is-hidden');
            newNode.querySelector('btn.delete').addEventListener('click', function() {
                newNode.querySelector(`input[name='alternative_title_global[]']`).value = "";
                newNode.querySelector(`input[name='alternative_title_valid_from_global[]']`).value = "";
                newNode.querySelector(`input[name='alternative_title_valid_to_global[]']`).value = "";
                newNode.classList.add('is-hidden');
            });

            template.parentNode.appendChild(newNode);
            newNode.querySelector(`input[name='alternative_title_global[]']`).focus();
        });

        // Set up deletion of alternative titles that exist already
        altTitleEntriesGlobal.forEach(function(entry) {
            entry.querySelector('btn.delete').addEventListener('click', function() {
                entry.querySelector(`input[name='alternative_title_global[]']`).value = "";
                entry.querySelector(`input[name='alternative_title_valid_from_global[]']`).value = "";
                entry.querySelector(`input[name='alternative_title_valid_to_global[]']`).value = "";
                entry.classList.add('is-hidden');
            });
        });
    }

    initLocalNote() {
        const buttonAddLocalNote = document.querySelector('#btn_add_local_note');
        const buttonRemoveLocalNote = document.querySelector('#btn_remove_local_note');
        const titleLocalNote = document.querySelector('#title_local_note');
        const columnsLocalNote = document.querySelector('#columns_local_note');

        if (buttonAddLocalNote) {
            buttonAddLocalNote.addEventListener('click', event => {
                event.preventDefault();
                titleLocalNote.classList.remove('is-hidden');
                columnsLocalNote.classList.remove('is-hidden');
                buttonRemoveLocalNote.classList.remove('is-hidden');
                buttonAddLocalNote.classList.add('is-hidden');
            });
        }

        if (buttonRemoveLocalNote) {
            buttonRemoveLocalNote.addEventListener('click', event => {
                event.preventDefault();
                document.querySelector('#local_note_de').value = "";
                document.querySelector('#local_note_en').value = "";
                titleLocalNote.classList.add('is-hidden');
                columnsLocalNote.classList.add('is-hidden');
                buttonRemoveLocalNote.classList.add('is-hidden');
                buttonAddLocalNote.classList.remove('is-hidden');
            });
        }
    }

    initShortDescription() {
        const buttonAddShortDescription = document.querySelector('#btn_add_short_description');
        const buttonRemoveShortDescription = document.querySelector('#btn_remove_short_description');
        const titleShortDescription = document.querySelector('#title_short_description');
        const columnsShortDescriptionGlobal = document.querySelector('.field-shortdescription.global');
        const columnsShortDescriptionLocal = document.querySelector('.field-shortdescription.local');

        buttonAddShortDescription.addEventListener('click', event => {
            event.preventDefault();

            titleShortDescription.classList.remove('is-hidden');
            buttonRemoveShortDescription.classList.remove('is-hidden');
            buttonAddShortDescription.classList.add('is-hidden');

            // Show local short description, if there is a local value, otherwise the global one.
            // But first check, if an organisation was selected.
            if (config.org_id !== "") {
                if (columnsShortDescriptionLocal.querySelector('#description_short_local_de').value === "" ||
                    columnsShortDescriptionLocal.querySelector('#description_short_local_en').value === "") {
                    columnsShortDescriptionGlobal.classList.remove('is-hidden');
                    this.setGlobalTag(titleShortDescription);
                } else {
                    columnsShortDescriptionLocal.classList.remove('is-hidden');
                    this.setLocalTag(titleShortDescription);
                }
            } else {
                columnsShortDescriptionGlobal.classList.remove('is-hidden');
            }
        });
        buttonRemoveShortDescription.addEventListener('click', event => {
            event.preventDefault();
            let inputs = document.querySelectorAll('#shortdescription-field .input');
            inputs.forEach(function(input) { input.value = ""; });
            titleShortDescription.classList.add('is-hidden');
            buttonRemoveShortDescription.classList.add('is-hidden');
            buttonAddShortDescription.classList.remove('is-hidden');
            columnsShortDescriptionGlobal.classList.add('is-hidden');
            columnsShortDescriptionLocal.classList.add('is-hidden');
        });
    }

    initLocalizationTags() {
        const localizationTags = document.querySelectorAll('.localization-tag');
        localizationTags.forEach(function(tag) {
            let localizationCheckbox = $(tag).children('.localization-checkbox');
            if (localizationCheckbox && localizationCheckbox.length > 0) {
                localizationCheckbox = localizationCheckbox[0];
            }

            tag.addEventListener("click", function(event) {
                let tags = event.target.parentElement,
                    localTag = tags.querySelector('.local-tag'),
                    globalTag = tags.querySelector('.global-tag'),
                    localField = document.querySelector('.' + tags.getAttribute('data-id') + '.local'),
                    globalField = document.querySelector('.' + tags.getAttribute('data-id') + '.global'),
                    localButton = document.querySelector('.' + tags.getAttribute('data-id') + '-add-button-container.local'),
                    globalButton = document.querySelector('.' + tags.getAttribute('data-id') + '-add-button-container.global');

                // Switch to local
                if (localField.classList.contains('is-hidden')) {
                    // Update localization tag
                    localTag.classList.remove('is-hidden');
                    globalTag.classList.add('is-hidden');
                    tags.querySelector('.tag.has-background-grey-lighter').classList.add('has-background-info');
                    tags.querySelector('.tag.has-background-grey-lighter').classList.remove('has-background-grey-lighter');
                    // Switch fields
                    localField.classList.remove('is-hidden');
                    globalField.classList.add('is-hidden');

                    $("#subjects_local").trigger("chosen:updated");

                    if (localButton !== null && globalButton !== null) {
                        localButton.classList.remove('is-hidden');
                        globalButton.classList.add('is-hidden');
                    }

                    $(localizationCheckbox).attr('checked', true);
                // Switch to global
                } else {
                    // Update localization tag
                    localTag.classList.add('is-hidden');
                    globalTag.classList.remove('is-hidden');
                    tags.querySelector('.tag.has-background-info').classList.add('has-background-grey-lighter');
                    tags.querySelector('.tag.has-background-info').classList.remove('has-background-info');
                    // Switch fields
                    localField.classList.add('is-hidden');
                    globalField.classList.remove('is-hidden');
                    if (localButton !== null && globalButton !== null) {
                        localButton.classList.add('is-hidden');
                        globalButton.classList.remove('is-hidden');
                    }

                    /*
                    localField.querySelectorAll('.input').forEach(function(input) { input.value = ""; });
                    localField.querySelectorAll('input[type=radio]').forEach(function(radio) { radio.checked = false; });
                    if (localButton !== null && globalButton !== null) {
                        localButton.classList.add('is-hidden');
                        globalButton.classList.remove('is-hidden');
                    }
                    */

                    $("#subjects_global").trigger("chosen:updated");

                    $(localizationCheckbox).attr('checked', false);
                    $(localizationCheckbox).removeAttr('checked');
                }

            });
        });
    }

    setLocalTag(parent) {
        let tags = parent.querySelector('.tags'),
            localTag = tags.querySelector('.local-tag'),
            globalTag = tags.querySelector('.global-tag');
        if (localTag.classList.contains('is-hidden')) {
            localTag.classList.remove('is-hidden');
            globalTag.classList.add('is-hidden');
            tags.querySelector('.tag.has-background-grey-lighter').classList.add('has-background-info');
            tags.querySelector('.tag.has-background-grey-lighter').classList.remove('has-background-grey-lighter');
        }
    }

    setGlobalTag(parent) {
        let tags = parent.querySelector('.tags'),
            localTag = tags.querySelector('.local-tag'),
            globalTag = tags.querySelector('.global-tag');
        if (globalTag.classList.contains('is-hidden')) {
            localTag.classList.add('is-hidden');
            globalTag.classList.remove('is-hidden');
            tags.querySelector('.tag.has-background-info').classList.add('has-background-grey-lighter');
            tags.querySelector('.tag.has-background-info').classList.remove('has-background-info');
        }
    }

    initTranslations() {
        const translatableText = new TranslatableText();
        translatableText.initTranslations('h-edit-resource');
    }

    initApiUrl() {
        const buttonAddApiUrlGlobal = document.querySelector('#btn_add_database_api_url_global');
        const authorEntriesGlobal = document.querySelectorAll('.field-api-url.global .api-url-entry');

        // GLOBAL
        // Set up add author button
        buttonAddApiUrlGlobal.addEventListener('click', event => {
            event.preventDefault();
            const entries = Array.from(document.querySelectorAll(".field-api-url.global .api-url-entry"));
            const template = entries[entries.length-1];
            const newNode = template.cloneNode(true);
            newNode.querySelector(`input[name='api_url_global[]']`).value = "";
            newNode.classList.remove('is-hidden');
            newNode.querySelector('btn.delete').addEventListener('click', function() {
                newNode.querySelector(`input[name='api_url_global[]']`).value = "";
                newNode.classList.add('is-hidden');
            });

            template.parentNode.appendChild(newNode);
            newNode.querySelector(`input[name='api_url_global[]']`).focus();
        });

        // Set up deletion of authors that exist already
        authorEntriesGlobal.forEach(function(entry) {
            // Check needed, because one field is available by default (without delete cross)
            if (entry.querySelector('btn.delete') !== null) {
                entry.querySelector('btn.delete').addEventListener('click', function() {
                    //entry.querySelector(`input[name='author_global_id[]'`).value = ""; // authors are not yet connected with id
                    entry.querySelector(`input[name='api_url_global[]']`).value = "";
                    entry.classList.add('is-hidden');
                });
            }
        });
    }

    initCopyButton() {
        const copyIdBtn = document.querySelector(".resource-id");
        if (copyIdBtn) {
            copyIdBtn.addEventListener('click', function() {
                const elements = document.getElementsByClassName("resource-id-text");
                if (elements.length > 0) {
                    const resourceIDTag = elements[0];

                    // resourceIDTag.select();
                    // resourceIDTag.setSelectionRange(0, 99999);
                    const id = resourceIDTag.innerText || resourceIDTag.textContent;
                    navigator.clipboard.writeText(id).then(r => console.log("Text copied!"));

                    $('.copy-id-tooltip').show();
                    setTimeout(() => {
                        $('.copy-id-tooltip').hide();
                    }, 1000);
                }
            });
        }
    }
}

document.addEventListener("DOMContentLoaded", function (event) {
    const createDatabase = new CreateDatabase();
    createDatabase.initEvents()
    createDatabase.initValidation()
});
