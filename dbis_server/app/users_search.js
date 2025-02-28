import $ from 'jquery';
import './scss/users_base.scss';
import '@fortawesome/fontawesome-free/js/all.js';
import './users_base';
import {Search} from './js/modules/search';
import {AutocompleteSearch} from './js/modules/autocomplete-search';
import {OfflineSearchableMultiselect} from './js/modules/offline-searchable-multiselect';

var submitTimeout;

var keywordsTimeout;

let subjectFilter = null;
let subjectAllFilter = null;
let publisherFilter = null;
let typeFilter = null;


document.addEventListener("DOMContentLoaded", function (event) {
    if (document.querySelector("#add-search-field")) {
        document.querySelector("#add-search-field").addEventListener("click", function(e) {
            e.preventDefault();
            addSearchField();
        });
    }
//

    initializeSubjectFilter();
    initializeResourceTypeFilter();
    //initializeHostFilter();
    initializeAuthorsFilter();
    initializePublishersFilter();
    initializeKeywordFilter();
    initializeAvailabilityFilter();
    initializeCountryFilter();
});
// ===========================================================================
// ======= Subject
// ===========================================================================

function initializeSubjectFilter()
{
    const root = document.querySelector("#subjects-filter");
    subjectFilter = new OfflineSearchableMultiselect(root);
}

// ===========================================================================
// ======= Availability Filter
// ===========================================================================

function initializeAvailabilityFilter()
{

}

// ===========================================================================
// ======= Resource Type Filter
// ===========================================================================

function initializeResourceTypeFilter()
{
    const root = document.querySelector("#resource-type-filter");
    typeFilter = new OfflineSearchableMultiselect(root);
}

// ===========================================================================
// ======= Host Filter
// ===========================================================================
function initializeHostFilter()
{

    const root = document.querySelector("#host-filter");
    typeFilter = new OfflineSearchableMultiselect(root);

    /*const root = document.querySelector("#host-filter");
    const template = root.querySelector("#template-dropdown-entry");
    const tags = document.querySelector("#host-selection");
    const autocompleteSearch = new AutocompleteSearch(root, template);

    // Filtered items for autocomplete elements have to be rendered inside
    // the respective template. However, the tags should appear elsewhere.
    // Therefore, we move the rendered tags to the new position here.
    const filteredItems = document.querySelector("#host-filtered") ?
        document.querySelector("#host-filtered") : null;

    // Initialize typeahead
    autocompleteSearch.ontypeahead = (q) => {
        getHosts(q)
                .then(data => autocompleteSearch.setDropdownList(data));
            }

        autocompleteSearch.onselect = (event) => {
            const id = parseInt(event.target.value);
            const label = event.target.innerHTML;
            // reset the autocomplete field
            autocompleteSearch.reset();
    
            // Check, if the keyword with this id has already been added
            const addedIds = Array.from(tags.querySelectorAll(".tags")).map(
                item => parseInt($(item).attr("id").substring(4)));
    
            if (!addedIds.includes(id))
            {
                const publisherSelection = $("#host-selection");
                // add chip
                const chip = createIDChip(tags, label, id, "filter-hosts[]");
                publisherSelection.append(chip);
    
                publisherSelection.find(`#tag-${id} a.is-delete`).on('click', (event) => {
                    event.target.parentNode.remove();
                });
            }
        }                

        tags.querySelectorAll(".tags").forEach((tag) => {
            $(tag).find(`a.is-delete`).on('click', (event) => {
                event.target.parentNode.remove();
            });
        });*/
}

async function getHosts(q, language="de")
{
    return fetch('/api/v1/hosts?language=' + language + '&q=' + q)
        .then(response => response.json())
        .then(data => data.hosts);
}

// ===========================================================================
// ======= Publishers Filter
// ===========================================================================

function initializePublishersFilter()
{
    
    const root = document.querySelector("#publishers-filter");
    const template = root.querySelector("#template-dropdown-entry");
    const tags = document.querySelector("#publisher-selection");
    const autocompleteSearch = new AutocompleteSearch(root, template);

    // Initialize typeahead
    autocompleteSearch.ontypeahead = (q) => {
        getPublishers(q)
                .then(data => autocompleteSearch.setDropdownList(data));
    }

    autocompleteSearch.onselect = (event) => {
        const id = parseInt(event.target.value);
        const label = event.target.innerHTML;
        // reset the autocomplete field
        autocompleteSearch.reset();

        // Check, if the keyword with this id has already been added
        const addedIds = Array.from(tags.querySelectorAll(".tags")).map(
            item => parseInt($(item).attr("id").substring(4)));

        if (!addedIds.includes(id))
        {
            const publisherSelection = $("#publisher-selection");
            // add chip
            const chip = createIDChip(tags, label, id, "filter-publishers[]");
            publisherSelection.append(chip);

            publisherSelection.find(`#tag-${id} a.is-delete`).on('click', (event) => {
                event.target.parentNode.remove();
            });
        }
    }

    tags.querySelectorAll(".tags").forEach((tag) => {
        $(tag).find(`a.is-delete`).on('click', (event) => {
            event.target.parentNode.remove();
        });
    });
    
}

async function getPublishers(q, language="de")
{
    return fetch('/api/v1/publishers?&q=' + q)
        .then(response => response.json())
        .then(data => data.publishers);
}

// ===========================================================================
// ======= Authors Filter
// ===========================================================================
function initializeAuthorsFilter()
{

    const root = document.querySelector("#author-filter");
    typeFilter = new OfflineSearchableMultiselect(root);

    /*const root = document.querySelector("#author-filter");
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

    // Reset the fallback-field
    root.querySelector("#author-text").value = null;*/
}

async function getAuthors(q, language="de")
{
    return fetch('/api/v1/authors?language=' + language + '&q=' + q)
        .then(response => response.json())
        .then(data => data.authors);
}

// ===========================================================================
// ======= Keyword Filter
// ===========================================================================

function initializeKeywordFilter()
{
    const root = document.querySelector("#keyword-filter");
    const template = root.querySelector("#template-dropdown-entry");
    const tags = document.querySelector("#keyword-selection");
    const autocompleteSearch = new AutocompleteSearch(root, template);

    // Initialize typeahead
    autocompleteSearch.ontypeahead = (q) => {
        getKeywords(q)
                .then(data => autocompleteSearch.setDropdownList(data));
    }

    autocompleteSearch.onselect = (event) => {
        const id = parseInt(event.target.value);
        const label = event.target.innerHTML;
        // reset the autocomplete field
        autocompleteSearch.reset();

        // Check, if the keyword with this id has already been added
        const addedIds = Array.from(tags.querySelectorAll(".tags")).map(
            item => parseInt($(item).attr("id").substring(4)));

        if (!addedIds.includes(id))
        {
            const keywordSelection = $("#keyword-selection");
            // add chip
            const chip = createChip(tags, label, id, "filter-keywords[]");
            keywordSelection.append(chip);

            keywordSelection.find(`#tag-${id} a.is-delete`).on('click', (event) => {
                event.target.parentNode.remove();
            });
        }
    }

    tags.querySelectorAll(".tags").forEach((tag) => {
        $(tag).find(`a.is-delete`).on('click', (event) => {
            event.target.parentNode.remove();
        });
    });

    /*
    const url = new URL(window.location.href);
    const params = new URLSearchParams(url.search);
    const filterKeywords = params.getAll('filter-keywords[]');

    // This is the initial creation of the chips/tags
    filterKeywords.forEach(filteredKeyword => {
        // TODO: Pass the correct GND id
        const id = 1;

        const keywordSelection = $("#keyword-selection");
        // add chip
        const chip = createChip(tags, filteredKeyword, id, "filter-keywords[]");
        keywordSelection.append(chip);

        keywordSelection.find(`#tag-${id} a.is-delete`).on('click', (event) => {
            event.target.parentNode.remove();
        });
    });
    */

    // Reset the fallback-field
    // root.querySelector("#keyword-text").value = null;
}

async function getKeywords(q)
{
    return fetch('/api/v1/keywords?q=' + q)
        .then(response => response.json())
        .then((data) => {           
            let uniqueObjects = data.keywords.filter((obj, index, self) => 
                index === self.findIndex((t) => t.match === obj.match)
            );

            return uniqueObjects
        });
}

// ===========================================================================
// ======= Country Filter
// ===========================================================================

function initializeCountryFilter()
{

    const root = document.querySelector("#country-filter");
    typeFilter = new OfflineSearchableMultiselect(root);

    /*
    const root = document.querySelector("#country-filter");
    const template = root.querySelector("#template-dropdown-entry");
    const tags = document.querySelector("#country-selection");
    const autocompleteSearch = new AutocompleteSearch(root, template);

    // Initialize typeahead
    autocompleteSearch.ontypeahead = (q) => {
        getCountries(q)
                .then(data => autocompleteSearch.setDropdownList(data));
    }

    autocompleteSearch.onselect = (event) => {
        const id = parseInt(event.target.value);
        const label = event.target.innerHTML;
        // reset the autocomplete field
        autocompleteSearch.reset();

        // Check, if the keyword with this id has already been added
        const addedIds = Array.from(tags.querySelectorAll(".tags")).map(
            item => parseInt($(item).attr("id").substring(4)));

        if (!addedIds.includes(id))
        {
            const countrySelection = $("#country-selection");
            // add chip
            const chip = createChip(tags, label, id, "filter-countries[]");
            countrySelection.append(chip);

            countrySelection.find(`#tag-${id} a.is-delete`).on('click', (event) => {
                event.target.parentNode.remove();
            });
        }
    }

    tags.querySelectorAll(".tags").forEach((tag) => {
        $(tag).find(`a.is-delete`).on('click', (event) => {
            event.target.parentNode.remove();
        });
    });
    */

    /*
    const url = new URL(window.location.href);
    const params = new URLSearchParams(url.search);
    const filterCountries = params.getAll('filter-countries[]');

    // This is the initial creation of the chips/tags
    filterCountries.forEach(filteredCountry => {
        // Workaround, so the chip is created correctly
        const id = 1;

        const countrySelection = $("#country-selection");
        // add chip
        const chip = createChip(tags, filteredCountry, id, "filter-countries[]");
        countrySelection.append(chip);

        countrySelection.find(`#tag-${id} a.is-delete`).on('click', (event) => {
            event.target.parentNode.remove();
        });
    });
    */
    // Reset the fallback-field
    // root.querySelector("#country-text").value = null;
}

async function getCountries(q, language="de")
{
    return fetch('/api/v1/countries?language=' + language + '&q=' + q)
        .then(response => response.json())
        .then(data => data.countries);
}

function createChip(parent, title, id=null, name=null)
{   
    // If an ID is passed, create a hidden input field, that is appended
    return `
    <div id="tag-${id}" class="tags has-addons is-align-items-flex-start mr-2">
      <span class="tag">${title}</span>
      <a class="tag is-delete"></a>
      ${id == null ? '': `<input type="hidden" value="${title}" name="${name}" />`}
    </div>`;
}

function createIDChip(parent, title, id=null, name=null)
{   
    // If an ID is passed, create a hidden input field, that is appended
    return `
    <div id="tag-${id}" class="tags has-addons is-align-items-flex-start mr-2">
      <span class="tag">${title}</span>
      <a class="tag is-delete"></a>
      ${id == null ? '': `<input type="hidden" value="${id}" name="${name}" />`}
    </div>`;
}

function addSearchField() {
    var searchField = document.createElement("div");
    searchField.innerHTML = document.querySelector("#search-field-template").innerHTML;
    let newNode = document.querySelector("#advanced-search-container").insertBefore(searchField,
        document.querySelector("#add-search-field"));
    newNode.querySelector("btn.delete").addEventListener("click", function(event) {deleteSearchField(event); });
}

function deleteSearchField(event) {
    event.target.parentElement.remove();
}
