import './scss/admin_base.scss';

const confirmUbrIdForm = document.querySelector("#delete-view-form");
const confirmBtn = confirmUbrIdForm.querySelector("button");
const confirmInput = confirmUbrIdForm.querySelector("input#confirm_ubrid");

confirmBtn.disabled = true;

function isUbrIdValid(control)
{
    const expected = control.dataset.expectedValue;
    const current = control.value;
    return (expected === current);
}

confirmInput.onkeyup = function (event)
{
    var isValid = isUbrIdValid(event.target);
    confirmBtn.disabled = !isValid;
}

class AdminManageDbisViews {
}