import $ from 'jquery';

class TranslatableText {
    constructor() {
    }

    initTranslations(containerClass) {
        $(`.${containerClass}`).on("click", '.button.translate', (event) => {
            event.preventDefault();

            event.stopPropagation();
            event.stopImmediatePropagation();

            const translateButton = event.currentTarget

            const cTranslate = translateButton.closest(".c-translate");

            const language = translateButton.className.split(" ").filter((className) => className.includes('language--'))[0]
            const textareaSource = cTranslate.querySelector(`.input[type=text].${language}`)

            const targetLang = language.includes('german') ? 'en' : 'de'
            const sourceLang = language.includes('german') ? 'de' : 'en'

            let languageTranslateTo = targetLang === 'de' ? 'language--german' : 'language--english'
            const textareaTarget = cTranslate.querySelector(`.input[type=text].${languageTranslateTo}`)

            if (textareaSource.value.length > 0) {
                $(textareaTarget).parent().addClass('is-loading')

                this._getTranslation(textareaSource.value, sourceLang, targetLang).then(translatedText => {
                    textareaTarget.value = translatedText

                    $(textareaTarget).parent().removeClass('is-loading')
                })
            }
        });
    }

    async _getTranslation(original, sourceLang, targetLang) {
        const url = `${config.translate_url}${sourceLang}/${targetLang}/${encodeURIComponent(original)}`

        const response = await fetch(url);
        const responseJSON = await response.json();

        return responseJSON["translation"]
    }
}

export {
    TranslatableText
}
