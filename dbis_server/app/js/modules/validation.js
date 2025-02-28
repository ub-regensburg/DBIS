//
//
// HELPER FUNTIONS FOR GENERATING ERROR TOOLTIPS
function appendErrorMessage(input, id, text) {
    if (input.parentNode.querySelector('.' + id) == null) {
        var textnode = document.createElement("div");
        textnode.innerHTML = text;
        textnode = input.parentNode.appendChild(textnode);
        textnode.classList.add(id);
        textnode.classList.add("help");
        textnode.classList.add("is-danger");
    }
}

function clearErrorMessage(input, id) {
    const messageBox = input.parentNode.querySelector("." + id);
    if (messageBox)
    {
        input.parentNode.removeChild(messageBox);
    }
}

//
//
// VALIDATORS

//
// EMAIL VALIDATOR

function updateSubmitButton(formInput) {
    const form = formInput.closest("form");
    const btnSubmit = form.querySelector('button[type="submit"]');
    btnSubmit.disabled = !form.checkValidity();
}

function addResourcesForCollectionValidator(formInput) {
    const msg = formInput.dataset.msg_required;
    const errcode = "err-required";

    function validate(formInput) {
        return formInput.querySelectorAll('.entry').length > 0;
    }

    function toggleError(isShowing) {
        if (isShowing) {
            formInput.classList.add('is-danger');
            // appendErrorMessage(formInput, errcode, msg);
            // formInput.setCustomValidity(msg);  // TODO: not possible on non-form elements
            document.querySelector('.validate-resources-for-collection-input').setCustomValidity(msg);
        } else {
            formInput.classList.remove('is-danger');
            // clearErrorMessage(formInput, errcode);
            // formInput.setCustomValidity("");  // TODO: not possible on non-form elements
            document.querySelector('.validate-resources-for-collection-input').setCustomValidity("");
        }
    }

    function update() {
        toggleError(!validate(formInput));
        updateSubmitButton(formInput);
    }

    const observer = new MutationObserver(mutations => {
        mutations.forEach(function (mutation) {
            if (mutation.type === 'childList') {
                update();
            }
        })
    })

    observer.observe(formInput, {
        attributes: true,
        childList: true,
        subtree: true
    });

    update();
}

function addEmailValidator(formInput) {
    // solution from https://stackoverflow.com/questions/46155/how-to-validate-an-email-address-in-javascript
    const re = /^(([^<>()[\]\.,;:\s@\"]+(\.[^<>()[\]\.,;:\s@\"]+)*)|(\".+\"))@(([^<>()[\]\.,;:\s@\"]+\.)+[^<>()[\]\.,;:\s@\"]{2,})$/i;
    const msg = formInput.dataset.msg_invalid_email;
    const errcode = "err-email-invalid";

    function validate(value) {
        if (!value) {
            return true;
        }
        return re.test(value.toLowerCase());
    }

    function toggleError(isShowing) {
        if (isShowing) {
            formInput.setCustomValidity(msg);
            formInput.classList.add('is-danger');
            appendErrorMessage(formInput, errcode, msg);
        } else {
            formInput.setCustomValidity("");
            formInput.classList.remove('is-danger');
            clearErrorMessage(formInput, errcode);
        }
    }

    function update() {
        toggleError(!validate(formInput.value));
        updateSubmitButton(formInput);
    }

    formInput.onkeyup = function (event) {
        update();
    };

    update();
}

//
// URL VALIDATOR

function addUrlValidator(formInput) {
    const msg = formInput.dataset.msg_invalid_url;
    const errcode = "err-invalid-url";

    function validate(value) {
        if (!value) {
            return true;
        }
        return URL.canParse(value);
    }

    function toggleError(isShowing) {
        if (isShowing) {
            formInput.setCustomValidity(msg);
            formInput.classList.add('is-danger');
            appendErrorMessage(formInput, errcode, msg);
        } else {
            formInput.setCustomValidity("");
            formInput.classList.remove('is-danger');
            clearErrorMessage(formInput, errcode);
        }
    }

    function update() {
        toggleError(!validate(formInput.value));
        updateSubmitButton(formInput);
    }

    formInput.addEventListener("keyup", (event) => update());

    update();
}

//
// ICON VALIDATOR

function addIconValidator(formInput) {
    // solution from https://stackoverflow.com/questions/46155/how-to-validate-an-email-address-in-javascript
    const re = /^.*\.(jpg|JPG|png|PNG)$/gi;
    const msg = formInput.dataset.msg_invalid_fileformat;
    const errcode = "err-invalid-filetype";

    function validate(value) {
        if (!value) {
            return true;
        }
        return re.test(value);
    }

    function toggleError(isShowing) {
        if (isShowing) {
            formInput.parentNode.querySelector('.file-name').classList.add('is-danger');
            formInput.parentNode.querySelector('.file-cta').classList.add('is-danger');
            appendErrorMessage(formInput.parentNode, errcode, msg);
            formInput.setCustomValidity(msg);
        } else {
            formInput.parentNode.querySelector('.file-name').classList.remove('is-danger');
            formInput.parentNode.querySelector('.file-cta').classList.remove('is-danger');
            clearErrorMessage(formInput.parentNode, errcode);
            formInput.setCustomValidity("");
        }
    }

    function update() {
        toggleError(!validate(formInput.files.length > 0 && formInput.files[0].name));
        updateSubmitButton(formInput);

    }

    formInput.addEventListener("keyup", (event) => update());

    update();
}

//
// ICON VALIDATOR

function addRequiredValidator(formInput) {
    if (formInput === null || formInput === undefined) {
        return;
    }

    switch (formInput.tagName.toLowerCase()) {
        case "input":
        case "textarea":
            _requireInput(formInput)
            break;
        case "select":
            _requireSelect(formInput)
            break;
    }
}

function _requireInput(formInput) {
    const msg = formInput.dataset.msg_required;
    const errcode = "err-required";

    function validate(value) {
        return (value !== "" && value !== null);
    }

    function toggleError(isShowing) {
        if (isShowing) {
            formInput.classList.add('is-danger');
            appendErrorMessage(formInput, errcode, msg);
            formInput.setCustomValidity(msg);
        } else {
            formInput.classList.remove('is-danger');
            clearErrorMessage(formInput, errcode);
            formInput.setCustomValidity("");
        }
    }

    function update() {
        toggleError(!validate(formInput.value));
        updateSubmitButton(formInput);
    }

    formInput.addEventListener("keyup", (event) => update());
    update();
}

function _requireSelect(formInput) {
    const msg = formInput.dataset.msg_required;
    const errcode = "err-required";

    function validate(select) {
        let checked = [...select.options].filter(option => option.selected).map(o => o.value);
        return checked.length > 0;
    }

    function toggleError(isShowing) {
        if (isShowing) {
            formInput.classList.add('is-danger');
            appendErrorMessage(formInput, errcode, msg);
            formInput.setCustomValidity(msg);
        } else {
            formInput.classList.remove('is-danger');
            clearErrorMessage(formInput, errcode);
            formInput.setCustomValidity("");
        }
    }

    function update() {
        toggleError(!validate(formInput));
        updateSubmitButton(formInput);
    }

    formInput.addEventListener("change", (event) => update());

    update();
}

//
//
// BIND VALIDATORS

function bindValidators(root = document) {
    for (let field of root.getElementsByClassName('validate-email')) {
        addEmailValidator(field);
    }

    for (let field of root.getElementsByClassName('validate-url')) {
        addUrlValidator(field);
    }

    for (let field of root.getElementsByClassName('validate-icon')) {
        addIconValidator(field);
    }

    for (let field of root.getElementsByClassName('validate-required')) {
        addRequiredValidator(field);
    }
}

function validateForm() {
    bindValidators(document);

    function onNodeAddedToDOM(n) {
        if (!n.querySelectorAll) {
            return;
        }
        bindValidators(n);
    }

    const observer = new MutationObserver(function (mutations) {
        mutations.forEach(m => {
            m.addedNodes.forEach(n => {
                if (document.contains(n)) {
                    onNodeAddedToDOM(n);
                }
            });
        });
    });

    observer.observe(document, {
        attributes: false,
        childList: true,
        characterData: true,
        subtree: true
    });
}



export {
    validateForm,
    appendErrorMessage,
    clearErrorMessage,
    addEmailValidator,
    addUrlValidator,
    addIconValidator,
    addRequiredValidator,
    addResourcesForCollectionValidator
};
