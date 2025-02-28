import './scss/users_base.scss';
import './users_base';
import '@fortawesome/fontawesome-free/js/all.js';
import $ from 'jquery';
import {OfflineSearchableMultiselect} from "./js/modules/offline-searchable-multiselect";


let submitTimeout;

let keywordsTimeout;

let subjectFilter = null;
let subjectAllFilter = null;
let publishersFilter = null;
let typeFilter = null;

document.addEventListener("DOMContentLoaded", function (event) {
    const accessButtons = document.querySelectorAll("button.display-access-info ");

    document.getElementById("sort_by").onchange = function () {
        document.getElementById("sort_by_form").submit();
    };

    if (document.getElementById("pagination_size")) {
        document.getElementById("pagination_size").onchange = function () {
            document.getElementById("query-form").submit();
        };
    }
    
    initFilters();
    accessButtons.forEach(btn => {
        btn.onclick = handleToggleAccessInfoEvent;
    });
});

function initFilters() {
    initializeSubjectFilter();
    initializeResourceTypeFilter();
    initializePublicationFormsFilter();
    // initializeHostFilter();
    // initializePublisherFilter();
    initializePublishersFilter()
    initializeKeywordFilter();
    initializeAvailabilityFilter();
    initializeCountryFilter();
    initializeTopDatabasesFilter();

    initSort();
    initPageSize();
}

function handleToggleAccessInfoEvent(event) {
    let btn = event.currentTarget; // Get the clicked button
    let hideText = btn.querySelector(".hide_top_dbs");
    let showText = btn.querySelector(".show_top_dbs");

    if (hideText && showText) {
        if (!hideText.classList.contains("hidden")) {
            hideText.classList.add("hidden");
            showText.classList.remove("hidden");
        } else {
            hideText.classList.remove("hidden");
            showText.classList.add("hidden");
        }
    }

    const contentBox = document.getElementsByClassName("access-info")[0];
    //const contentBox = event.target.parentNode.parentNode.nextElementSibling;
    const icon = event.currentTarget.querySelector("svg");
    contentBox.classList.toggle("hidden");
    if (contentBox.classList.contains("hidden")) {
      icon.classList.remove("fa-chevron-up");
      icon.classList.add("fa-chevron-down");
    } else {
      icon.classList.remove("fa-chevron-down");
      icon.classList.add("fa-chevron-up");
    }
  }

// ===========================================================================
// ======= Availability Filter
// ===========================================================================

function initializeAvailabilityFilter() {
    const root = document.querySelector("#availability-filter");

    if (!root) {
        return;
    }

    const options = document.querySelectorAll("#availability-filter input[type='checkbox']");

    options.forEach(function (opt) {
        opt.addEventListener("click", function () {
            document.querySelector("#query-form").submit();
        });
    });
}

// ===========================================================================
// ======= Subject
// ===========================================================================

function initializeSubjectFilter() {
    const root = document.querySelector("#subjects-filter");

    if (!root) {
        return;
    }

    subjectFilter = new OfflineSearchableMultiselect(root);
    subjectFilter.onselect = (event) => {
        document.querySelector("#query-form").submit();
    }
}

// ===========================================================================
// ======= Resource Type Filter
// ===========================================================================

function initializeResourceTypeFilter() {
    const root = document.querySelector("#resource-type-filter");

    if (!root) {
        return;
    }

    const typeFilter = new OfflineSearchableMultiselect(root);
    typeFilter.onselect = (event) => {
        document.querySelector("#query-form").submit();
    }
}

function initializePublicationFormsFilter() {
    const root = document.querySelector("#publication-form-filter");

    if (!root) {
        return;
    }

    typeFilter = new OfflineSearchableMultiselect(root);
    typeFilter.onselect = (event) => {
        document.querySelector("#query-form").submit();
    }
}

// ===========================================================================
// ======= Top Databases
// ===========================================================================

function initializeTopDatabasesFilter() {
    const topDatabasesFilter = $("#top-databases-filter");
    const toggleDatabases = $("#toggle-top-databases");

    if ((!topDatabasesFilter || topDatabasesFilter.length < 1) && toggleDatabases) {
        toggleDatabases.on('change', function () {
            const showTopDatabases = toggleDatabases.prop('checked');
            if (showTopDatabases) {
                const hiddenInput = $('<input>')
                    .attr('id', 'show-top-databases')
                    .attr('type', 'hidden')
                    .attr('name', 'show-top-databases') 
                    .attr('value', '1');
                $("#query-form").append(hiddenInput);
            } else {
                const hiddenInput = $('#show-top-databases');
                if (hiddenInput) {
                    hiddenInput.remove();
                }
            }
            document.querySelector("#query-form").submit();
        });
    } else {
        topDatabasesFilter.on('change', function () {
            if (topDatabasesFilter.prop('checked')) // if changed state is "CHECKED"
            {
                console.log("Set sort to top databases");
                
                $("#sort_by option:checked").each(function(index) {
                    $(this).removeAttr("selected");
                })
                $('#sort_by').append('<option value="' + 2 + '" selected>Top-Datenbanken</option>');
                
            } else {

            }

            document.querySelector("#query-form").submit();
        });
    }
}

// ===========================================================================
// ======= Publishers Filter
// ===========================================================================
function initializePublishersFilter() {
    const root = document.querySelector("#publishers-filter");

    if (!root) {
        return;
    }

    publishersFilter = new OfflineSearchableMultiselect(root);
    publishersFilter.onselect = (event) => {
        document.querySelector("#query-form").submit();
    }    
    /*const root = document.querySelector("#publishers-filter");

    if (!root) {
        return;
    }

    const template = root.querySelector("#template-dropdown-entry");
    const autocompleteSearch = new AutocompleteSearch(root, template);

    // Filtered items for autocomplete elements have to be rendered inside
    // the respective template. However, the tags should appear elsewhere.
    // Therefore, we move the rendered tags to the new position here.
    const filteredItems = document.querySelector("#publisher-filtered") ?
        document.querySelector("#publisher-filtered") : null;

    // Initialize typeahead
    autocompleteSearch.ontypeahead = (q) => {
        getPublishers(q)
            .then(data => autocompleteSearch.setDropdownList(data));
    }

    // Initialize adding tags on selecting typeahead option
    autocompleteSearch.onselect = (event) => {
        document.querySelector("#query-form").submit();
    }

    // Reset the fallback-field
    root.querySelector("#publishers-text").value = null;*/
}

/*
async function getPublishers(q) {
    return fetch('/api/v1/publishers?&q=' + q)
        .then(response => response.json())
        .then(data => data.publishers);
}*/


// ===========================================================================
// ======= Authors Filter
// ===========================================================================
function initializePublisherFilter() {
    const root = document.querySelector("#author-filter");

    if (!root) {
        return;
    }

    const template = root.querySelector("#template-dropdown-entry");
    const autocompleteSearch = new AutocompleteSearch(root, template);

    // Filtered items for autocomplete elements have to be rendered inside
    // the respective template. However, the tags should appear elsewhere.
    // Therefore, we move the rendered tags to the new position here.
    const filteredItems = document.querySelector("#author-filtered") ?
        document.querySelector("#author-filtered") : null;

    // Initialize typeahead
    autocompleteSearch.ontypeahead = (q) => {
        getAuthors(q)
            .then(data => autocompleteSearch.setDropdownList(data));
    }

    // Initialize adding tags on selecting typeahead option
    autocompleteSearch.onselect = (event) => {
        document.querySelector("#query-form").submit();
    }

    // Reset the fallback-field
    root.querySelector("#author-text").value = null;
}

async function getAuthors(q, language = "de") {
    return fetch('/api/v1/authors?language=' + language + '&q=' + q)
        .then(response => response.json())
        .then(data => data.authors);
}

function initializeKeywordFilter() {
    const root = document.querySelector("#keywords-filter");

    if (!root) {
        return;
    }

    const keywordFilter = new OfflineSearchableMultiselect(root);
    keywordFilter.onselect = (event) => {
        document.querySelector("#query-form").submit();
    }
}

async function getKeywords(q, language = "de") {
    return fetch('/api/v1/keywords?language=' + language + '&q=' + q)
        .then(response => response.json())
        .then(data => data.keywords);
}

function initializeCountryFilter() {
    const root = document.querySelector("#countries-filter");

    if (!root) {
        return;
    }

    const autocompleteSearch = new OfflineSearchableMultiselect(root);

    // Initialize adding tags on selecting typeahead option
    autocompleteSearch.onselect = (event) => {
        document.querySelector("#query-form").submit();
    }
}

async function getCountries(q, language = "de") {
    return fetch('/api/v1/countries?language=' + language + '&q=' + q)
        .then(response => response.json())
        .then(data => data.countries);
}

function initSort() {
    if (document.getElementById("sort_by")) {
        document.getElementById("sort_by").onchange = function () {
            document.getElementById("query-form").submit();
        };
    }
}

function initPageSize() {
    if (document.getElementById("pagination_size")) {
        document.getElementById("pagination_size").onchange = function () {
            document.getElementById("query-form").submit();
        };
    }
}