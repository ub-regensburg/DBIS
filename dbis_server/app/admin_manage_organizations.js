import './scss/admin_base.scss';
import '@fortawesome/fontawesome-free/js/all.js';
import {validateForm} from './js/modules/validation';
import './admin_base.js';


document.addEventListener("DOMContentLoaded", function (event) {
    const searchInput = document.getElementById("search-field");
    const searchForm = document.getElementById("search-form");
    const searchInputLength = searchInput.value.length;
    const searchSortBy  = document.getElementById("search-sortby");
    const searchSortDirection = document.getElementById("search-sortdirection");
    const searchCriterionButtons = document.querySelectorAll("a.sort-criterion");
    let timer;

    searchInput.addEventListener('input', function (evt) {
        clearTimeout(timer);
        timer = setTimeout(function () {
            searchForm.submit();
        }, 750);
    });
    
    function setSorting(sortBy, sortDir)
    {   
        if(searchSortBy.value === sortBy) {
            // simply toggle direction, if criterion has been the same
            searchSortDirection.value = (searchSortDirection.value === 'asc') ? 'desc' : 'asc';
        }
        searchSortBy.value = sortBy;
        searchForm.submit();
    }

    searchCriterionButtons.forEach(function(button) {
        button.addEventListener('click', function(){
           setSorting(button.dataset.sortby);
        });
    });
    
    searchInput.focus();
    searchInput.setSelectionRange(searchInputLength, searchInputLength);

    validateForm();

});