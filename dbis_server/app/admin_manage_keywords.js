import '@fortawesome/fontawesome-free/js/all.js'
import $ from 'jquery';
import './admin_base.js'
import './scss/admin_base.scss'
import {AutocompleteSearch} from "./js/modules/autocomplete-search";
import {TranslatableText} from './js/modules/translatable_text.js';

class ManageKeywords {
    constructor() {

    }

    initEvents() {
        this.initKeywords();
        this.initTranslations();
    }

    initKeywords() {
        const keywordEntries = document.querySelectorAll('.field-keywords .keyword-entry');

        // Set up deletion of keywords that exist already
        keywordEntries.forEach((entry) => {
            // Check needed, because one field is available by default (without delete cross)
            if (entry.querySelector('btn.delete') !== null) {
                entry.querySelector('btn.delete').addEventListener('click', function() {
                    entry.querySelector(`input[name='keyword_id[]']`).value = "";
                    entry.querySelector(`input[name='keyword_de[]']`).value = "";
                    entry.querySelector(`input[name='keyword_en[]']`).value = "";
                    entry.classList.add('is-hidden');
                });
            }
            const root = entry.querySelector(".keyword-typeahead--de");
            this._initTypeahead(root);
            this._initSave(entry);
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

    initTranslations() {
        const translatableText = new TranslatableText();
        translatableText.initTranslations('field-keywords');
    }

    _initSave(entry) {
        const card = $(entry).parent().closest('.card');
        const saveButton = card.find('.save-keyword');

        if (saveButton.length > 0) {
            const that = this;
            saveButton.on('click', function () {
                const keywordEntry = card.find('.keyword-entry');

                const id = parseInt(keywordEntry.find('input[name="keyword_id[]"]').val())
                const resourceId = parseInt(keywordEntry.find('input[name="resource_id[]"]').val())
                const titleDe = keywordEntry.find('input[name="keyword_de[]"]').val()
                const titleEn = keywordEntry.find('input[name="keyword_en[]"]').val()
                const externalId = keywordEntry.find('input[name="external_id[]"]').val()

                if (titleDe.length > 0 && titleEn.length > 0 && externalId.length > 0) {
                    that._saveKeyword(id, titleDe, titleEn, externalId, resourceId).then(r => {
                        console.log("Saved successfully!")

                        let buttonLabel = "Gespeichert";
                        if (window.config.lang === "en") {
                            buttonLabel = "Saved";
                        }
                        saveButton.text(buttonLabel);
                        saveButton.prop("disabled", true);
                        saveButton.addClass("disabled");
                        saveButton.off("click");
                    });
                }
            });
        }
    }

    async _saveKeyword(id, titleDe, titleEn, externalId, resourceId)
    {
        const data = {
            'id': id,
            'resource_id': resourceId,
            'title_de': titleDe,
            'title_en': titleEn,
            'external_id': externalId,
            'keyword_system': 'gnd',
            'lang': window.config.lang
        }

        fetch(`/api/v1/keyword/${resourceId}`, {
            method: 'PUT',
            headers: {
                'Accept': 'application/json, text/plain, */*',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        }).then(res => res.json())
            .then(res => console.log(res));
    }
}

document.addEventListener("DOMContentLoaded", function (event) {
    const manageKeywords = new ManageKeywords();
    manageKeywords.initEvents()
});
