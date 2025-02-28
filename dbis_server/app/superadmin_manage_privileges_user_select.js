import './admin_base'
import './scss/admin_base.scss';
import '@fortawesome/fontawesome-free/js/all.js';

class SuperadminManagePrivilegesUserSelect {

    constructor() {
         this.inputSearchField = document.getElementById("search-field");
         this.items = document.querySelectorAll(".user-item");
         
         
         this.inputSearchField.addEventListener("keyup", (event) => {
            this.update(); 
         });
         
         this.update();
    }
    
    update() {
        this.filterList(this.inputSearchField.value);
    }
    
    filterList(searchTerm) {
        this.items.forEach(item => {
            const prename = item.querySelector(".prename").innerHTML.trim();
            const surname = item.querySelector(".surname").innerHTML.trim();
            const id = item.querySelector(".user-id").innerHTML.trim();
            const privileges = item.querySelectorAll(".privilege.tag");
            const organizationNamesAndCities = Array.from(privileges).reduce(function(accumulator, privilege) {
                const organization = JSON.parse(privilege.dataset['organization']);                
                return accumulator + " " 
                        + organization['name'] + " "
                        + organization['city'];
            }, "");
            const haystack = `${prename} ${surname} ${id} ${organizationNamesAndCities}`;
            const isMatchStrings = this.matchStrings(searchTerm, haystack);
            item.classList.toggle("hidden", !isMatchStrings);
        });
    }    
    
    matchStrings(needle, haystack, threshold = 0.95)
    {
        const searchTokens = this.tokenize(needle);
        const lowercaseHaystack = haystack.toLowerCase();
        const matchingTokens = searchTokens.filter((token) => {
            return lowercaseHaystack.includes(token);
        });
        return matchingTokens.length / searchTokens.length >= threshold;
    }    

    tokenize(strInput)
    {
        return strInput.toLowerCase().split(" ");
    }

}

document.addEventListener("DOMContentLoaded", function (event) {
    const page = new SuperadminManagePrivilegesUserSelect();
});