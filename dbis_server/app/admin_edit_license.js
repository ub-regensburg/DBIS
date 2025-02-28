import $ from 'jquery';
import './scss/admin_base.scss';
import './scss/pages/admin_edit_license.scss';
import '@fortawesome/fontawesome-free/js/all.js';
import './admin_base.js';
import './js/modules/extensible-list';
import './js/modules/accordion';
import {validateForm} from './js/modules/validation';
import {TranslatableText} from './js/modules/translatable_text.js';

class AdminLicenseForm {

    selectedVendor;
    selectedPublisher;

    constructor () {
        this.accessBody = null;

        this.initializeConstants();
        this.initializeControls();
        this.initializeListeners();
        this.initializeState();
        this.initializeObserver();

        // this.initializeVendors();
        this.initializePublishers();

        this.initTranslations();

        this.initLabelsModal();

        this.initLocalOverwrite();

        this.initLocalAccess();
    }

    onAccessItemAdded(item)
    {
        const that = this;

        function handleInput(event) {
            const searchTerm = event.target.value.toLowerCase();
            const options =  Array.from(item.querySelectorAll("option:not(.exclude-from-filter)"));
            options.forEach((option) => {
                const isMatch = option.innerHTML.toLowerCase().includes(searchTerm);
                isMatch ? option.classList.remove("filter-hidden") : option.classList.add("filter-hidden");
            });
        }

        item.querySelector("input.host.search").addEventListener("keyup", handleInput);
        item.querySelector("input.host.search").addEventListener("change", handleInput);

        item.querySelector("select.host").addEventListener("change", this.onAccessHostSelected);

        item.querySelector(".host-create-group button").addEventListener("click",
            (event) => {
                event.preventDefault();
                AdminLicenseForm.toggleCreateHostElementsVisible(item, false);
        });

        item.querySelector(".host-create-group").style.display = "none";

        Array.from(item.querySelectorAll(".jsonly")).forEach(item => {
            item.classList.remove("jsonly");
        });
    }

    onAccessHostSelected(event)
    {
        if(event.target.value == "::createnewhost")
        {
            event.target.value = "";
            AdminLicenseForm.toggleCreateHostElementsVisible(event.target.parentNode.parentNode, true);
        } else {
            const selected = event.target.options[event.target.selectedIndex].text;
            event.target.parentNode.querySelector("input.host.search").value = selected;
        }
    }

    /**
     * Show "new host" fields, if the selected host is not known
     * @param {type} accessItem
     * @param {type} isVisible
     * @returns {undefined}
     */
    static toggleCreateHostElementsVisible(accessItem, isVisible)
    {
        accessItem.querySelector(".host-select-group").style.display = !isVisible ? "block" : "none";
        accessItem.querySelector(".host-create-group").style.display = isVisible ? "flex" : "none";
        if(isVisible) {
            const selectControl = accessItem.querySelector("input.host");
            const value = selectControl.value;
            Array.from(accessItem.querySelectorAll(".host-new")).map(item => {
                item.value = value;
                item.disabled=false;
            });
            accessItem.querySelector(".host-select").value = "";
            Array.from(accessItem.querySelectorAll(".host-new"))[0].focus();
        } else {
            Array.from(accessItem.querySelectorAll(".host-new")).map(item => item.value = "");
            accessItem.querySelector(".host-select").select();
        }
    }

    isSelectedHostInOptions(host)
    {
        const optionValues = this.hostOptions.map(item => item.value);
        return optionValues.reduce((acc, value) => {
            return (value === host) ? true : acc;
        }, false);
    }

    onLicenseTypeSelected(type, isInitializingForm=false)
    {
        if (this.FREE_LICENSE_TYPE_IDS.includes(type))
        {
            this.handleFreeLicenseType();
        } else {
            if (!isInitializingForm) {
                this.handleNonFreeLicenseType(type);
            }
        }

        this.handleNotesAndDatesForOrganisation(type);

        this.handleFidInformation(type);

        if (!isInitializingForm) {
            this.adaptAccesses(type);
        }
    }

    adaptAccesses(type) {
        if (type == 2) {
            $('.c-is-global').each(function( index ) {
                $(this).hide();
            });
            $("input[name='is_global[]']").each(function( index ) {
                $(this).prop("checked", false);
            });
        } else {
            $(".c-is-global").each(function( index ) {
                $(this).show()
            });
        }

        if (type == 1) {
            $("input[name='is_global[]']").each(function( index ) {
                $(this).removeAttr("disabled");
            });
        }
    }

    handleNotesAndDatesForOrganisation(type) {
        let text = ""
        if (type == 2) {
            text = this.datesHeadline.data('other-dates-local')
            this.notesAndDatesForOrganisation.hide();
        } else {
            text = this.datesHeadline.data('other-dates')
            this.notesAndDatesForOrganisation.show();
        }

        this.datesHeadline.text(text)
    }

    handleDatesForOrganisation(type) {
        if (type == 2) {
            this.datesForOrganisation.hide();
        } else {
            this.datesForOrganisation.show();
        }
    }

    handleFreeLicenseType()
    {
        this.licenseFormSelect.value = "null";
        this.licenseFormSelect.disabled = true;

        this.licenseFormParallelUsers.value = null;
        this.licenseFormParallelUsers.disabled = true;
    }

    handleNonFreeLicenseType(type)
    {
        this.licenseFormSelect.disabled = false;
        this.licenseFormParallelUsers.disabled = false;
    }

    initializeConstants() {
        this.FREE_LICENSE_TYPE_IDS = ["1"];
        this.ORGANIZATION_ID = this.organizationIdField ? this.organizationIdField.value : null;
    }

    initializeControls() {
        this.licenseAccessList = document.querySelector(".license-access-list");
        this.licenceTypeSelect = document.querySelector("select[name='licenseType']");
        this.licenseFormSelect = document.querySelector("select[name='licenseForm']");
        this.licenseFormParallelUsers = document.querySelector("input[name='parallel_users']");
        this.accessesList = document.querySelector("div.extensible-list[data-namespace='accesses']");
        this.accessesItems = document.querySelectorAll(".access-form");
        this.organizationIdField = document.querySelector("input[name='organizationId']");
        this.hostOptions = Array.from(document.querySelectorAll("datalist#hosts option"));
        this.notesForOrganisation = $('.notes-for-organisation');
        this.datesForOrganisation = $('.dates-for-organisation');
        this.notesAndDatesForOrganisation = $('.ajax-form-for-organisation');
        this.datesHeadline = $('#h-lic-dates');
    }

    initializeListeners() {
        const that = this;
        
        if ($('.edit-license-form button[type="submit"]').length > 0) {
            validateForm();
        }
        
        if (this.licenceTypeSelect) {
            this.licenceTypeSelect.onchange = (evt)  => {
                that.onLicenseTypeSelected(evt.target.value);
            };
            const type = parseInt($(this.licenceTypeSelect).val());
            this.adaptAccesses(type);
            this.handleFidInformation(type);
        } else {
            const licenceTypeHidden = document.querySelector("input[name='licenseType']");
            const type = parseInt($(licenceTypeHidden).val());
            this.adaptAccesses(type);
            this.handleFidInformation(type);
        }

        $("form.edit-license-form").on( "submit", (event) => {
            $('form.edit-license-form input.is-global').each(function(index) {
                let hiddenValue = null;
                if ($(this).prop('checked')) {
                    hiddenValue = 1;
                } else {
                    hiddenValue = 0;
                }
                $('<input>').attr({
                    type: 'hidden',
                    name: $(this).attr('name'),
                    value: hiddenValue
                }).appendTo($(this).parent()); // Append hidden input to the form
        
                // Remove the original checkbox
                $(this).remove();
            });

            $('form.edit-license-form input.is-main').each(function(index) {
                let hiddenValue = null;
                if ($(this).prop('checked')) {
                    hiddenValue = 1;
                } else {
                    hiddenValue = 0;
                }
                $('<input>').attr({
                    type: 'hidden',
                    name: $(this).attr('name'),
                    value: hiddenValue
                }).appendTo($(this).parent()); // Append hidden input to the form
        
                // Remove the original checkbox
                $(this).remove();
            });

            $('form.edit-license-form input.is-access-hidden').each(function(index) {
                let hiddenValue = null;
                if ($(this).prop('checked')) {
                    hiddenValue = 1;
                } else {
                    hiddenValue = 0;
                }
                $('<input>').attr({
                    type: 'hidden',
                    name: $(this).attr('name'),
                    value: hiddenValue
                }).appendTo($(this).parent()); // Append hidden input to the form
        
                // Remove the original checkbox
                $(this).remove();
            });

            $(':disabled').each(function(e) {
                $(this).removeAttr('disabled');
            })

            return true;
        });
        
        this.accessesItems.forEach(item => {
            $(item).addClass('is-active');
            this.onAccessItemAdded(item);
        });
    }

    handleFidInformation(type) {
        type = parseInt(type);

        $('#fid-information').addClass('is-hidden');

        switch(type) {
            case 4:
                $('#fid-information').removeClass('is-hidden');
                break;
        }
    }

    initializeState() {
        if (this.licenceTypeSelect) {
            this.onLicenseTypeSelected(this.licenceTypeSelect.value, true);
        }
    }

    initializeObserver() {
        const that = this;
        const observer = new MutationObserver(function (mutations) {
            mutations.forEach(m => {
                m.addedNodes.forEach(n => {
                    if (document.contains(n) &&
                            n.classList &&
                            n.classList.contains("access-form")) {
                        that.onAccessItemAdded(n);
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

    initializeVendors()
    {
        var item = document.querySelector(".vendor-container");
        this.selectedVendor = item.querySelector("select.vendor").selectedIndex;

        function handleInput(event) {
            const searchTerm = event.target.value.toLowerCase();
            const options =  Array.from(item.querySelectorAll("option:not(.exclude-from-filter)"));
            options.forEach((option) => {
                const isMatch = option.innerHTML.toLowerCase().includes(searchTerm);
                isMatch ? option.classList.remove("filter-hidden") : option.classList.add("filter-hidden");
            });
        }

        item.querySelector("input.vendor.search").addEventListener("keyup", handleInput);
        item.querySelector("input.vendor.search").addEventListener("change", handleInput);

        Array.from(item.querySelectorAll("select.vendor option")).forEach(option => {
            option.addEventListener("click", () => this.onVendorSelected(event, this.selectedVendor));
        });

        item.querySelector(".vendor-create-group button").addEventListener("click",
            (event) => {
                event.preventDefault();
                AdminLicenseForm.toggleCreateVendorElementsVisible(item, false);
        });

        item.querySelector(".vendor-create-group").style.display = "none";

        Array.from(item.querySelectorAll(".jsonly")).forEach(item => {
            item.classList.remove("jsonly");
        });
    }

    onVendorSelected(event, previouslySelectedVendor)
    {
        if(event.target.parentNode.value == "::createnewvendor")
        {
            event.target.parentNode.value = "";
            AdminLicenseForm.toggleCreateVendorElementsVisible(event.target.parentNode.parentNode.parentNode, true);
        } else {
            // Make deselection possible with click on selected item
            if (event.target.parentNode.selectedIndex != previouslySelectedVendor) {
                const selected = event.target.parentNode.options[event.target.parentNode.selectedIndex].text;
                event.target.parentNode.parentNode.querySelector("input.vendor.search").value = selected;
                this.selectedVendor = event.target.parentNode.selectedIndex;
            } else {
                event.target.parentNode.value = "";
                event.target.parentNode.parentNode.querySelector("input.vendor.search").value = "";
            }
        }
    }

    /**
     * Show "new vendor" fields, if the selected vendor is not known
     * @param {type} vendorItem
     * @param {type} isVisible
     * @returns {undefined}
     */
    static toggleCreateVendorElementsVisible(vendorItem, isVisible)
    {
        vendorItem.querySelector(".vendor-select-group").style.display = !isVisible ? "block" : "none";
        vendorItem.querySelector(".vendor-create-group").style.display = isVisible ? "flex" : "none";
        if(isVisible) {
            const selectControl = vendorItem.querySelector("input.vendor");
            const value = selectControl.value;
            Array.from(vendorItem.querySelectorAll(".vendor-new")).map(item => {
                item.value = value;
                item.disabled=false;
            });
            vendorItem.querySelector(".vendor-select").value = "";
            Array.from(vendorItem.querySelectorAll(".vendor-new"))[0].focus();
        } else {
            Array.from(vendorItem.querySelectorAll(".vendor-new")).map(item => item.value = "");
            vendorItem.querySelector(".vendor-select").select();
        }
    }

    initializePublishers()
    {
        var item = document.querySelector(".publisher-container");
        this.selectedPublisher = item.querySelector("select.publisher").selectedIndex;

        function handleInput(event) {
            const searchTerm = event.target.value.toLowerCase();
            const options =  Array.from(item.querySelectorAll("option:not(.exclude-from-filter)"));
            options.forEach((option) => {
                const isMatch = option.innerHTML.toLowerCase().includes(searchTerm);
                isMatch ? option.classList.remove("filter-hidden") : option.classList.add("filter-hidden");
            });
        }

        item.querySelector("input.publisher.search").addEventListener("keyup", handleInput);
        item.querySelector("input.publisher.search").addEventListener("change", handleInput);

        Array.from(item.querySelectorAll("select.publisher option")).forEach(option => {
            option.addEventListener("click", () => this.onPublisherSelected(event, this.selectedPublisher));
        });

        item.querySelector(".publisher-create-group button").addEventListener("click",
            (event) => {
                event.preventDefault();
                AdminLicenseForm.toggleCreatePublisherElementsVisible(item, false);
        });

        item.querySelector(".publisher-create-group").style.display = "none";

        Array.from(item.querySelectorAll(".jsonly")).forEach(item => {
            item.classList.remove("jsonly");
        });
    }

    onPublisherSelected(event, previouslySelectedPublisher)
    {
        if(event.target.parentNode.value == "::createnewpublisher")
        {
            event.target.parentNode.value = "";
            AdminLicenseForm.toggleCreatePublisherElementsVisible(event.target.parentNode.parentNode.parentNode, true);
        } else {
            // Make deselection possible with click on selected item
            if (event.target.parentNode.selectedIndex != previouslySelectedPublisher) {
                const selected = event.target.parentNode.options[event.target.parentNode.selectedIndex].text;
                event.target.parentNode.parentNode.querySelector("input.publisher.search").value = selected;
                this.selectedPublisher = event.target.parentNode.selectedIndex;
            } else {
                event.target.parentNode.value = "";
                event.target.parentNode.parentNode.querySelector("input.publisher.search").value = "";
            }
        }
    }

    /**
     * Show "new publisher" fields, if the selected publisher is not known
     * @param {type} publisherItem
     * @param {type} isVisible
     * @returns {undefined}
     */
    static toggleCreatePublisherElementsVisible(publisherItem, isVisible)
    {
        publisherItem.querySelector(".publisher-select-group").style.display = !isVisible ? "block" : "none";
        publisherItem.querySelector(".publisher-create-group").style.display = isVisible ? "flex" : "none";
        if(isVisible) {
            const selectControl = publisherItem.querySelector("input.publisher");
            const value = selectControl.value;
            Array.from(publisherItem.querySelectorAll(".publisher-new")).map(item => {
                item.value = value;
                item.disabled=false;
            });
            publisherItem.querySelector(".publisher-select").value = "";
            Array.from(publisherItem.querySelectorAll(".publisher-new"))[0].focus();
        } else {
            Array.from(publisherItem.querySelectorAll(".publisher-new")).map(item => item.value = "");
            publisherItem.querySelector(".publisher-select").select();
        }
    }

    initTranslations() {
        const translatableText = new TranslatableText();
        translatableText.initTranslations('h-edit-license');
    }

    initLabelsModal() {
        $('.h-edit-license').on("click", '.open-labels-modal', (event) => {
            const clickedModalButton = $(event.target);
            const modalTargetSelector = clickedModalButton.data('target')

            $(`#${modalTargetSelector}`).addClass('is-active')

            this.accessBody = clickedModalButton.parent().closest('.accordion-body')
        });    
        
        $('.h-edit-license').on("click", '.empty-labels', (event) => {
            const clickedEmptyButton = $(event.target);

            let accessBody = clickedEmptyButton.parent().closest('.accordion-body')
            accessBody.find('input[name="label_id[]"]').val('')

            accessBody.find('.access-label-de').val('')
            accessBody.find('.access-label-en').val('')

            accessBody.find('.access-label-long-de').val('')
            accessBody.find('.access-label-long-en').val('')

            accessBody.find('.access-label-longest-de').val('')
            accessBody.find('.access-label-longest-en').val('')
        }); 

        $('#labels-modal .delete').on("click", (event) => {
            $('#labels-modal').removeClass('is-active')
        });  

        $('#labels-modal').on("click", '.add-default-label', (event) => {
            console.log(event)

            const clickedButton = $(event.target);

            const labelContainer = clickedButton.parent().closest('.label-container')

            const labelId = labelContainer.data('label-id')

            const labelDe = labelContainer.find(`.label-de-${labelId}`).text()
            const labeEn = labelContainer.find(`.label-en-${labelId}`).text()

            const labelLongDe = labelContainer.find(`.label-long-de-${labelId}`).text()
            const labeLongEn = labelContainer.find(`.label-long-en-${labelId}`).text()

            const labelLongestDe = labelContainer.find(`.label-longest-de-${labelId}`).text()
            const labeLongestEn = labelContainer.find(`.label-longest-en-${labelId}`).text()

            if (this.accessBody) {
                this.accessBody.find('.access-label-de').val(labelDe)
                this.accessBody.find('.access-label-en').val(labeEn)

                this.accessBody.find('.access-label-long-de').val(labelLongDe)
                this.accessBody.find('.access-label-long-en').val(labeLongEn)

                this.accessBody.find('.access-label-longest-de').val(labelLongestDe)
                this.accessBody.find('.access-label-longest-en').val(labeLongestEn)

                this.accessBody.find('input[name="label_id[]"]').val(labelId)

                this.accessBody = null
            }

            $('#labels-modal').removeClass('is-active')
        }); 
    }

    initLocalOverwrite() {
        const saveLocalDataButton = $('.save-local-data');

        if (saveLocalDataButton) {
            saveLocalDataButton.on('click', function() {
                let internal_notes_for_org_de = $('#internal_notes_for_org_de').val().trim()
                let internal_notes_for_org_en = $('#internal_notes_for_org_en').val().trim()
                let external_notes_for_org_de = $('#external_notes_for_org_de').val().trim()
                let external_notes_for_org_en = $('#external_notes_for_org_en').val().trim()

                let aquired_by_organisation = $('#aquired_by_organisation').val()
                let cancelled_by_organisation = $('#cancelled_by_organisation').val()
                let last_check_by_organisation = $('#last_check_by_organisation').val()
                
                saveLocalDataButton.addClass('is-loading');

                const data = {
                    org: window.config.org_id,
                    license_id: config.license_id,
                    internal_notes_for_org_de,
                    internal_notes_for_org_en,
                    external_notes_for_org_de,
                    external_notes_for_org_en,
                    aquired_by_organisation,
                    cancelled_by_organisation,
                    last_check_by_organisation
                };

                fetch(`/api/v1/license-localisation`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json, text/plain, */*',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                }).then(res => res.json())
                    .then(res => {
                        saveLocalDataButton.removeClass('is-loading');

                        $('.save-local-data-tooltip').show();
                        setTimeout(() => {
                            $('.save-local-data-tooltip').hide();
                        }, 1000);
                });
            });
        }
    }

    initLocalAccess() {
        const saveLocalDataButton = $('.save-local-access');

        if (saveLocalDataButton) {
            
        }
    }
}

document.addEventListener("DOMContentLoaded", function (event) {
    // Appends validators and dom listeners regarding validation
    const form = new AdminLicenseForm();
});
