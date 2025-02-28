import './scss/users_base.scss';
import './users_base';
import '@fortawesome/fontawesome-free/js/all.js';
import {OfflineSearchableMultiselect} from "./js/modules/offline-searchable-multiselect";

document.addEventListener("DOMContentLoaded", function (event) {
    const entries = document.querySelectorAll(".subject-name-field");
    const searchField = document.getElementById("subject-name-search");

    function init() 
    {
        searchField.focus();
        searchField.selectionStart = searchField.value.length;
        searchField.selectionEnd = searchField.value.length;
        filterSubjectList(searchField.value);
    }
    
    function tokenize(strInput)
    {
        return strInput.toLowerCase().split(" ");
    }
    
    /**
     * 
     * @param {string} needle
     * @param {string} haystack
     * @param {float} threshold Higher threshold will match more selectively
     * @returns {Number}
     */
    function matchStrings(needle, haystack, threshold=0.95)
    {
        const searchTokens = tokenize(needle);
        const lowercaseHaystack = haystack.toLowerCase();
        const matchingTokens = searchTokens.filter((token) => {
           return lowercaseHaystack.includes(token); 
        });    
        return matchingTokens.length / searchTokens.length >= threshold;        
    }
    
    function filterSubjectList(term="") {
        entries.forEach(entry => {
            if(!term || term === "")
            {
                entry.closest(".subject-row").classList.remove("hidden");
                return;                
            }            
            matchStrings(term, entry.innerHTML)?
                entry.closest(".subject-row").classList.remove("hidden") : 
                entry.closest(".subject-row").classList.add("hidden");
        });
    }

    if (searchField) {
        searchField.addEventListener("keyup", function(event) {
            filterSubjectList(event.target.value);
        });

        init();
    }
});