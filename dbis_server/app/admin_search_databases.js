import $ from 'jquery';
import './scss/admin_base.scss';
import '@fortawesome/fontawesome-free/js/all.js';
import './admin_base';
import {Search} from './js/modules/search';
import {AutocompleteSearch} from './js/modules/autocomplete-search';
import {OfflineSearchableMultiselect} from './js/modules/offline-searchable-multiselect';

var submitTimeout;

var keywordsTimeout;

let subjectFilter = null;
let typeFilter = null;


document.addEventListener("DOMContentLoaded", function (event) {
    if (document.querySelector("#add-search-field")) {
        document.querySelector("#add-search-field").addEventListener("click", function(e) {
            e.preventDefault();
            addSearchField();
        });
    }
 
    initializeSubjectFilter();
    initializeResourceTypeFilter();
    //initializeHostFilter();
    initializePublishersFilter();
    initializeAuthorsFilter();
    initializeKeywordFilter();
    initializeAvailabilityFilter();
    initializeCountryFilter();

    initReset();
});

function clearAllTags()
{
    var tags = document.querySelectorAll(".tag");
    tags.forEach(tag => {
        if (tag.parentElement.getAttribute("id") === "availability-tags") {
            // Only remove last availability filter, since all others are standard
            if (tag.previousSibling != null) {
                tag.remove();
            }
        } else {
            tag.remove();
        }
    });

    // reset the list of the subject and type filters when tags are cleared
    if (subjectFilter) {
        subjectFilter.reset();
        subjectFilter.update();
    }
    if (typeFilter) {
        typeFilter.reset();
        typeFilter.update();
    }
}

// ===========================================================================
// ======= Subject
// ===========================================================================

function initializeSubjectFilter()
{
    const root = document.querySelector("#subjects-filter");
    subjectFilter = new OfflineSearchableMultiselect(root);

    /*
    const tags = document.querySelector("#subject-tags");
    subjectFilter.onselect = (event) => {
        // subjectFilter.reset();
        tags.innerHTML = "";

        subjectFilter.getSelectedOptions().forEach(opt => {
            const id = opt.value
            const chip = createChip(tags, opt.innerHTML, (event) => {
                subjectFilter.deselectOption(id);
                chip.remove();
            });
        });
    }

    // This is the initial creation of the chips/tags
    subjectFilter.getSelectedOptions().forEach(opt => {
        const id = opt.value
        const chip = createChip(tags, opt.innerHTML, (event) => {
            subjectFilter.deselectOption(id);
            chip.remove();
        });
    });
    */
}

// ===========================================================================
// ======= Availability Filter
// ===========================================================================

function initializeAvailabilityFilter()
{
    const root = document.querySelector("#availability-filter");
    const options = document.querySelectorAll("#availability-filter input[type='checkbox']");
    const tags = document.querySelector("#availability-tags");

    options.forEach(function(opt) {
        opt.addEventListener("click", function() {
            tags.innerHTML = "";

            options.forEach(opt => {
                if (opt.checked) {
                    const id = opt.name
                    const chip = createChip(tags, opt.parentElement.innerText, (event) => {
                        document.querySelector("#availability-filter input[name='" + id + "']").checked = false;
                        chip.remove();
                    });
                }
            });
        });
    });

    // This is the initial creation of the chips/tags
    options.forEach(opt => {
        if (opt.checked) {
            const id = opt.name
            const chip = createChip(tags, opt.parentElement.innerText, (event) => {
                document.querySelector("#availability-filter input[name='" + id + "']").checked = false;
                chip.remove();
            });
        }
    });
}

// ===========================================================================
// ======= Resource Type Filter
// ===========================================================================

function initializeResourceTypeFilter()
{
    const root = document.querySelector("#resource-type-filter");
    typeFilter = new OfflineSearchableMultiselect(root);

    /*
    const tags = document.querySelector("#resource-type-tags");
    typeFilter.onselect = (event) => {
        // subjectFilter.reset();
        tags.innerHTML = "";

        typeFilter.getSelectedOptions().forEach(opt => {
            const id = opt.value
            const chip = createChip(tags, opt.innerHTML, (event) => {
                typeFilter.deselectOption(id);
                chip.remove();
            });
        });
    }

    // This is the initial creation of the chips/tags
    typeFilter.getSelectedOptions().forEach(opt => {
        const id = opt.value
        const chip = createChip(tags, opt.innerHTML, (event) => {
            typeFilter.deselectOption(id);
            chip.remove();
        });
    });
    */
}

// ===========================================================================
// ======= Host Filter
// ===========================================================================
function initializeHostFilter()
{

    const root = document.querySelector("#host-filter");
    typeFilter = new OfflineSearchableMultiselect(root);

   /* const root = document.querySelector("#host-filter");
    const template = root.querySelector("#template-dropdown-entry");
    const tags = document.querySelector("#host-tags");
    const autocompleteSearch = new AutocompleteSearch(root, template);

    // Filtered items for autocomplete elements have to be rendered inside
    // the respective template. However, the tags should appear elsewhere.
    // Therefore, we move the rendered tags to the new position here.
    const filteredItems = document.querySelector("#host-filtered") ?
        document.querySelector("#host-filtered") : null;
    if (filteredItems) {
        tags.innerHTML = filteredItems.innerHTML;
        filteredItems.innerHTML = "";
        filteredItems.style.display = "none";
    }

    // Add "delete" functionality to existing keyword tags
    Array.from(tags.querySelectorAll(".tag button"))
            .forEach(item => {
                item.onclick = (event) => {
                    event.target.parentNode.remove();
                }
            });

    // Initialize typeahead
    autocompleteSearch.ontypeahead = (q) => {
        getHosts(q)
                .then(data => autocompleteSearch.setDropdownList(data));
    }

    // Initialize adding tags on selecting typeahead option
    autocompleteSearch.onselect = (event) => {
        const id = event.target.value;
        const label = event.target.innerHTML;
        // reset the autocomplete field
        autocompleteSearch.reset();

        // Check, if the keyword with this id has already been added
        const addedIds = Array.from(tags.querySelectorAll(".tag input")).map(
                item => item.value);
        if (!addedIds.includes(id))
        {
            // add chip
            const chip = createChip(tags, label, (event) => {
                event.target.parentNode.remove();
            }, id, "host-ids[]");
        }
    }

    // Reset the fallback-field
    root.querySelector("#host-text").value = null;*/
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

async function getPublishers(q)
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
    const tags = document.querySelector("#author-tags");
    const autocompleteSearch = new AutocompleteSearch(root, template);

    // Filtered items for autocomplete elements have to be rendered inside
    // the respective template. However, the tags should appear elsewhere.
    // Therefore, we move the rendered tags to the new position here.
    const filteredItems = document.querySelector("#author-filtered") ?
        document.querySelector("#author-filtered") : null;
    if (filteredItems) {
        tags.innerHTML = filteredItems.innerHTML;
        filteredItems.innerHTML = "";
        filteredItems.style.display = "none";
    }

    // Add "delete" functionality to existing keyword tags
    Array.from(tags.querySelectorAll(".tag button"))
            .forEach(item => {
                item.onclick = (event) => {
                    event.target.parentNode.remove();
                }
            });

    // Initialize typeahead
    autocompleteSearch.ontypeahead = (q) => {
        getAuthors(q)
                .then(data => autocompleteSearch.setDropdownList(data));
    }

    // Initialize adding tags on selecting typeahead option
    autocompleteSearch.onselect = (event) => {
        const id = event.target.value;
        const label = event.target.innerHTML;
        // reset the autocomplete field
        autocompleteSearch.reset();

        // Check, if the keyword with this id has already been added
        const addedIds = Array.from(tags.querySelectorAll(".tag input")).map(
                item => item.value);
        if (!addedIds.includes(id))
        {
            // add chip
            const chip = createChip(tags, label, (event) => {
                event.target.parentNode.remove();
            }, id, "author-ids[]");
        }
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

    // Filtered items for autocomplete elements have to be rendered inside
    // the respective template. However, the tags should appear elsewhere.
    // Therefore, we move the rendered tags to the new position here.
    const filteredItems = document.querySelector("#keyword-filtered") ?
        document.querySelector("#keyword-filtered") : null;
    if (filteredItems) {
        tags.innerHTML = filteredItems.innerHTML;
        filteredItems.innerHTML = "";
        filteredItems.style.display = "none";
    }

    // Add "delete" functionality to existing keyword tags
    tags.querySelectorAll(".tags").forEach((tag) => {
        $(tag).find(`a.is-delete`).on('click', (event) => {
            event.target.parentNode.remove();
        });
    });

    // Initialize typeahead
    autocompleteSearch.ontypeahead = (q) => {
        getKeywords(q)
                .then((data) => {
                    autocompleteSearch.setDropdownList(data)
                });
    }

    // Initialize adding tags on selecting typeahead option
    autocompleteSearch.onselect = (event) => {
        const id = event.target.value;
        const label = event.target.innerHTML;
        // reset the autocomplete field
        autocompleteSearch.reset();

        // Check, if the keyword with this id has already been added
        const addedIds = Array.from(tags.querySelectorAll(".tag input")).map(
                item => item.value);
        if (!addedIds.includes(id))
        {
            // const tagContainer = $('#keyword-selection');
            // add chip
            const chip = createChip(tags, label, (event) => {
                event.target.parentNode.remove();
            }, id, "filter-keywords[]");
        }
    }

    /*
    const url = new URL(window.location.href);
    const params = new URLSearchParams(url.search);
    const filterKeywords = params.getAll('filter-keywords[]');

    // This is the initial creation of the chips/tags
    filterKeywords.forEach(filteredKeyword => {
        // TODO: Pass the correct GND id
        const id = 1;
        const chip = createChip(tags, filteredKeyword, (event) => {
            event.target.parentNode.remove();
        }, id, "filter-keywords[]");
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

    // Filtered items for autocomplete elements have to be rendered inside
    // the respective template. However, the tags should appear elsewhere.
    // Therefore, we move the rendered tags to the new position here.
    const filteredItems = document.querySelector("#country-filtered") ?
        document.querySelector("#country-filtered") : null;
    if (filteredItems) {
        tags.innerHTML = filteredItems.innerHTML;
        filteredItems.innerHTML = "";
        filteredItems.style.display = "none";
    }

    // Add "delete" functionality to existing country tags
    tags.querySelectorAll(".tags").forEach((tag) => {
        $(tag).find(`a.is-delete`).on('click', (event) => {
            event.target.parentNode.remove();
        });
    });

    // Initialize typeahead
    autocompleteSearch.ontypeahead = (q) => {
        getCountries(q)
                .then(data => autocompleteSearch.setDropdownList(data));
    }

    // Initialize adding tags on selecting typeahead option
    autocompleteSearch.onselect = (event) => {
        const id = event.target.value;
        const label = event.target.innerHTML;
        // reset the autocomplete field
        autocompleteSearch.reset();

        // Check, if the country with this id has already been added
        const addedIds = Array.from(tags.querySelectorAll(".tag input")).map(
                item => item.value);
        if (!addedIds.includes(id))
        {
            // add chip
            const chip = createChip(tags, label, (event) => {
                event.target.parentNode.remove();
            }, id, "filter-countries[]");
        }
    }
        */

    /*
    const url = new URL(window.location.href);
    const params = new URLSearchParams(url.search);
    const filterCountries = params.getAll('filter-countries[]');

    // This is the initial creation of the chips/tags
    filterCountries.forEach(filteredKeyword => {
        // Workaround, so the chip is created correctly
        const id = 1;
        const chip = createChip(tags, filteredKeyword, (event) => {
            event.target.parentNode.remove();
        }, id, "filter-countries[]");
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

//
//
//


// This should be universal for all filters!
function createChip(parent, title, onDeleteCallback, id=null, name=null)
{
    const newNode = document.createElement("span");
    const delBtn = document.createElement("btn");
    const txtContent = document.createElement("span");
    newNode.classList.add("tag");
    // newNode.classList.add("is-primary");
    newNode.classList.add("is-light");
    newNode.classList.add("mr-2");
    delBtn.classList.add("delete");
    delBtn.classList.add("is-small");
    delBtn.onclick = onDeleteCallback;
    txtContent.innerHTML = title;
    newNode.appendChild(txtContent);
    newNode.appendChild(delBtn);
    parent.appendChild(newNode);

    // If an ID is passed, create a hidden input field, that is appended
    if (id)
    {
        const input = document.createElement("input");
        input.type = "hidden";
        // input.value = id;
        input.value = title;
        input.name = name;
        newNode.appendChild(input);
    }

    /*
    // Scroll to top only if it is a input text field
    if (parent.id.includes('keyword') || parent.id.includes('author') || parent.id.includes('host') || parent.id.includes('country')) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    */

    return newNode;
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

function initReset() {
    if (document.querySelector("input[type='reset']")) {
        // clear tags if reset button is used
        document.querySelector("input[type='reset']").addEventListener("click", clearAllTags);
    }
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
