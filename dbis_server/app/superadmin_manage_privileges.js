import './admin_base'
import './scss/admin_base.scss';
import '@fortawesome/fontawesome-free/js/all.js';
import {validateForm} from './js/modules/validation';

class SuperadminManagePrivileges {

    constructor() {
        const that = this;
        
        this.addButton = document.getElementById("btn-add-privilege");
        this.privilegeModal = document.getElementById("modal-add-privilege");
        this.privilegeItems = document.querySelectorAll(".privilege-item");        
        
        
        this.inputOrgAutocomplete = document.getElementById("organization");
        this.dropdownOrgs = document.getElementById("dropdown-orgs");
        this.inputOrganization = document.getElementById("organization-id");
        this.submitButton = document.getElementById("btn-new-priv");
        this.selectPrivilegeType = document.getElementById("privilege-type");
        this.organizationOptions = this.dropdownOrgs.querySelectorAll(".dropdown-item");

        this.selectPrivilegeType.addEventListener("change", () => {
            this.update();
        });

        this.inputOrgAutocomplete.addEventListener("keyup", () => {
            that.inputOrganization.value = "";
            that.update();
        });

        this.organizationOptions.forEach(item => {
            item.addEventListener("click", evt => {
                this.selectOrganization(evt.target.value, event.target.innerHTML.trim());
            });
        });
        
        this.addButton.addEventListener("click", evt => {
            this.privilegeModal.classList.add("is-active");
            evt.preventDefault();
        });
        
        this.privilegeItems.forEach(item => { 
            item.querySelector(".btn-info").addEventListener("click", evt => {
                const data = item.dataset;
                const modal = new PrivilegeTypeInfoModal(
                        data.privilegetypeDescription,
                        data.privilegetypeName
                        );
            });
        });

        this.update();
    }

    update() {
        if (this.inputOrgAutocomplete.value !== ""
                && this.inputOrganization.value === "")
        {
            this.filterList(this.inputOrgAutocomplete.value);
            this.dropdownOrgs.classList.add("is-active");
        } else {
            this.dropdownOrgs.classList.remove("is-active");
        }

        this.submitButton.disabled = !this.isFormValid();

    }

    filterList(term) {
        this.organizationOptions.forEach(option => {
            option.classList.toggle("hidden", !this.matchStrings(term, option.innerHTML.trim()) && !this.matchIDs(term, option.value.trim()));
        });
    }

    /**
     * 
     * @param {string} needle
     * @param {string} haystack
     * @param {float} threshold Higher threshold will match more selectively
     * @returns {Number}
     */
    matchStrings(needle, haystack, threshold = 0.95)
    {
        const searchTokens = this.tokenize(needle);
        const lowercaseHaystack = haystack.toLowerCase();
        const matchingTokens = searchTokens.filter((token) => {
            return lowercaseHaystack.includes(token);
        });
        return matchingTokens.length / searchTokens.length >= threshold;
    }    

    matchIDs(searchTerm, ubrID) {
        return searchTerm === "" ? false : ubrID.toLowerCase().includes(searchTerm.toLowerCase());
    }

    tokenize(strInput)
    {
        return strInput.toLowerCase().split(" ");
    }

    isFormValid() {
        // A superadmin does not require an organization to be specified
        if (this.selectPrivilegeType.selectedOptions[0].value === "1000") {
            return true;
        }
        // Admin privilege requires a target organization to be specified
        return this.inputOrganization.value !== "";
    }

    selectOrganization(id, text) {
        this.inputOrganization.value = id;
        this.inputOrgAutocomplete.value = text;
        this.update();
    }
}

class PrivilegeTypeInfoModal {
    constructor(text, heading) {
        this.modal = document.getElementById("modal-info-privilege-type");
        this.lblInfo = document.getElementById("modal-info-privilege-type-description");
        this.lblHeader = document.getElementById("modal-info-privilege-type-name");
        
        this.btnClose = this.modal.querySelector("button.delete");
        this.btnCloseBottom = this.modal.querySelector("button.close-dialog");
        
        this.lblInfo.innerHTML = text;
        this.lblHeader.innerHTML = heading;
        
        this.modal.classList.add("is-active");
        
        this.btnClose.addEventListener("click", () => {
           this.destroy(); 
        });
        this.btnCloseBottom.addEventListener("click", () => {
           this.destroy(); 
        });
        
    }
    
    destroy() {
        this.modal.classList.remove("is-active");        
    }
}

document.addEventListener("DOMContentLoaded", function (event) {
    const page = new SuperadminManagePrivileges();
});