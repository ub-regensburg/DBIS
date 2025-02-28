import $ from 'jquery';
import './scss/users_details.scss';
import './js/modules/expandable';
import './users_base.js';
import { addDropdownInteractivity } from './users_base.js'

document.addEventListener("DOMContentLoaded", function (event) {
    const accessInfos = document.querySelectorAll("li.access-info");
    const accessButtons = document.querySelectorAll("button.display-access-info ");

    accessInfos.forEach(el => {
       // el.classList.add("hidden");
    });

    accessButtons.forEach(btn => {
        btn.onclick = handleToggleAccessInfoEvent;
    });

    document.addEventListener('keyup', e => {
        if(e.key === "ArrowRight")
            browseNext();
        if(e.key === "ArrowLeft")
            browsePrevious();
        if(e.key === "Escape")
            returnToResults();
    })

    const copyIdBtn = document.querySelector(".resource-id");
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

    $('.link-to').on("click", (event) => {
        event.preventDefault();

        const hostname = window.location.origin;
        const ubrId = $('.dropdown-trigger .selected-org').attr('data-ubrid');

        let redirectToUserView = true;
        
        let resourceId = null;
        if ($('#link-to-org').length > 0) {
            resourceId = $('#link-to-org').data("resource-id");
        } else {
            resourceId = $('#link-to-user').data("resource-id");
        }

        if (ubrId && ubrId.length > 0) {
            let link = `${hostname}/${ubrId}/resources/${resourceId}`;

            if (link) {
                if (redirectToUserView) {
                    window.open(link, '_blank');
                } else {
                    window.location.href = link;
                }
            }
        }
    });

    $('.select-organisation-with-license').on("click", (event) => {
        event.preventDefault();

        const orgName = $(event.target).text().trim();
        const ubrId = $(event.target).attr('value');

        $('.dropdown-trigger .selected-org').html(orgName);
        $('.dropdown-trigger .selected-org').attr('data-ubrid', ubrId);
    })

    // const detailDropdown = document.querySelector("#dropdown-other-orgs");
    // addDropdownInteractivity(detailDropdown);
});

function handleToggleAccessInfoEvent(event) {
    const contentBox = event.target.parentNode.parentNode.nextElementSibling;
    const icon = event.target.querySelector("svg");
    contentBox.classList.toggle("hidden");

    if(contentBox.classList.contains("hidden"))
    {
        icon.classList.remove("fa-chevron-up");
        icon.classList.add("fa-chevron-down");
    } else {
        icon.classList.remove("fa-chevron-down");
        icon.classList.add("fa-chevron-up");
    }
}

function browseNext()
{
    const btnNext = document.querySelector("#btn-browse-next");
    if(btnNext)
        btnNext.click();
}

function browsePrevious()
{
    const btnPrev = document.querySelector("#btn-browse-previous");
    if(btnPrev)
        btnPrev.click();
}

function returnToResults()
{
    const btnEscape = document.querySelector("#btn-return-to-results");
    if(btnEscape)
        btnEscape.click();
}
