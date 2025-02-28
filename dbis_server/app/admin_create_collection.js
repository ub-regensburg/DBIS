import '@fortawesome/fontawesome-free/js/all.js'
import './admin_base.js'
import './scss/admin_base.scss'
import './scss/pages/admin_create_collection.scss'
import {PickerList} from './js/modules/picker_list_for_collection.js';
import {TranslatableText} from './js/modules/translatable_text.js';


class CreateCollection {
    constructor() {

    }

    initEvents() {
        this.initPicker();
        this.initTranslations();
    }

    initTranslations() {
        const translatableText = new TranslatableText();
        translatableText.initTranslations('h-edit-collection');
    }

    initPicker() {
        const sourceList = document.getElementById("resources-source-list");
        const targetList = document.getElementById("resources-target-list");

        if (sourceList)
        {
            new PickerList(sourceList, targetList, {
                "sort_by": 1  // ALPHABETICAL_SORTING: 1, RELEVANCE: 0
            });
        }
    }
}

document.addEventListener("DOMContentLoaded", function (event) {
    const createCollection = new CreateCollection();
    createCollection.initEvents()
});
