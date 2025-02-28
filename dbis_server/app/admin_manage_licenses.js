import $ from 'jquery';
import './scss/admin_base.scss';
import '@fortawesome/fontawesome-free/js/all.js';
import {validateForm} from './js/modules/validation';
import './admin_base';


document.addEventListener("DOMContentLoaded", function (event) {
    $('.link-to').on("click", (event) => {
        event.preventDefault();

        const hostname = window.location.origin;
        const ubrId = $('.dropdown-trigger .selected-org').attr('data-ubrid');

        let redirectToUserView = null;
        let resourceId = null;
        if ($('#link-to-org').length > 0) {
            redirectToUserView = false;
            resourceId = $('#link-to-org').data("resource-id");
        } else {
            redirectToUserView = true;
            resourceId = $('#link-to-user').data("resource-id");
        }

        let link = null;
        if (redirectToUserView) {
            link = `${hostname}/${ubrId}/resources/${resourceId}`;
        } else {
            link = `${hostname}/admin/manage/${ubrId}/resources/${resourceId}/licenses/`;
        }

        if (ubrId && ubrId.length > 0) {
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
});