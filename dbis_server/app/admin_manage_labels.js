import $ from 'jquery';
import '@fortawesome/fontawesome-free/js/all.js'
import './admin_base.js'
import './scss/admin_base.scss'
import './scss/pages/admin_manage_labels.scss'
import {TranslatableText} from './js/modules/translatable_text.js';

class ManageLabels {
    constructor() {
        this.template = $('#template')
    }

    initEvents() {
        this.initTranslations()
        this.initButtons()
    }

    initTranslations() {
        const translatableText = new TranslatableText();
        translatableText.initTranslations('h-manage-drafts');
    }

    initButtons() {
        $('.add-label').on('click', (event) => {
            const emptyLabel = this.template.children().clone()
            emptyLabel.removeClass('is-hidden')
            $('.new-labels-contaniner').append(emptyLabel)
        })

        $('.merge-labels').on('click', (event) => {
            event.preventDefault()
            event.stopPropagation()

            const clickedButton = $(event.target);
            const labelContainer = clickedButton.parent().closest('.label-container')

            labelContainer.find('.container-merge-labels').toggleClass('is-hidden')
        })
    }
}

document.addEventListener("DOMContentLoaded", function (event) {
    const manageLabels = new ManageLabels();
    manageLabels.initEvents()
});
