import './scss/users_base.scss';
import '@fortawesome/fontawesome-free/js/all.js';
import $ from 'jquery';
import './users_base';
import {Search} from './js/modules/search';
import {AutocompleteSearch} from './js/modules/autocomplete-search';
import {OfflineSearchableMultiselect} from './js/modules/offline-searchable-multiselect';

let submitTimeout;

let keywordsTimeout;

let subjectFilter = null;
let subjectAllFilter = null;
let publishersFilter = null;
let typeFilter = null;

document.addEventListener("DOMContentLoaded", function (event) {
    const accessHelpButtons = document.querySelectorAll("button.text-toggler-button");

    accessHelpButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            if (window.getComputedStyle(button.nextElementSibling).display === "none" || button.nextElementSibling.style.display === "none") {
                button.nextElementSibling.style.display = "block";
                button.querySelector("span.symbol-arrow").style.transform = 'rotate(180deg)';
            } else {
                button.nextElementSibling.style.display = "none";
                button.querySelector("span.symbol-arrow").style.transform = '';
            }
        });
    });

    initializeSubjectFilter();
    initializeResourceTypeFilter();
    initializeLicenseTypeFilter();
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

    initRelationships();
});

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
// ======= Top Databases
// ===========================================================================

function initializeTopDatabasesFilter() {
    const topDatabasesFilter = $("#top-databases-filter");

    if (!topDatabasesFilter || topDatabasesFilter.length < 1) {
        return;
    }

    topDatabasesFilter.on('change', function () {
        document.querySelector("#query-form").submit();
    });
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
// ======= Resource Type Filter
// ===========================================================================

function initializeResourceTypeFilter() {
    const root = document.querySelector("#resource-type-filter");

    if (!root) {
        return;
    }

    typeFilter = new OfflineSearchableMultiselect(root);
    typeFilter.onselect = (event) => {
        document.querySelector("#query-form").submit();
    }
}

function initializeLicenseTypeFilter() {
    const root = document.querySelector("#license-type-filter");

    if (!root) {
        return;
    }

    typeFilter = new OfflineSearchableMultiselect(root);
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
// ======= Host Filter
// ===========================================================================
function initializeHostFilter() {
    const root = document.querySelector("#host-filter");

    if (!root) {
        return;
    }

    const template = root.querySelector("#template-dropdown-entry");
    const autocompleteSearch = new AutocompleteSearch(root, template);

    // Initialize typeahead
    autocompleteSearch.ontypeahead = (q) => {
        getHosts(q)
            .then(data => autocompleteSearch.setDropdownList(data));
    }

    // Initialize adding tags on selecting typeahead option
    autocompleteSearch.onselect = (event) => {
        document.querySelector("#query-form").submit();
    }

    // Reset the fallback-field
    root.querySelector("#host-text").value = null;
}

async function getHosts(q, language = "de") {
    return fetch('/api/v1/hosts?language=' + language + '&q=' + q)
        .then(response => response.json())
        .then(data => data.hosts);
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

// ===========================================================================
// ======= Keyword Filter
// ===========================================================================

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

// ===========================================================================
// ======= Country Filter
// ===========================================================================

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

async function fetchRelationships(options = {}) {
    const fetchUrl = `/api/v1/relations/${options.resourceId}`;

    let result = null;
    let url = new URL(fetchUrl, document.baseURI);
    let json = null;
    if (!options.url) {
        if (options.language) {
            url.searchParams.set("language", options.language);
        }
    }

    try {
        result = await fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        json = await result.json();

        return json;
    } catch (err) {
        console.log(err)
        return null
    }
}

function initRelationships() {
    const boxes = $('.search-loading-element .box');

    for (let box of boxes) {
        let resourceId = null;

        const elemId = $(box).find(".resource-id");
        if (elemId) {
            resourceId = elemId.data('id');
        }

        if (resourceId) {
            fetchRelationships({'resourceId': resourceId}).then((data) => {
                
                const resources = data.resources;
                const relations = data.relations;
                const hasParents = $(`.box-${resourceId} .has-parents`)
                const hasChildren = $(`.box-${resourceId} .has-children`)
                const isRelated = $(`.box-${resourceId} .is-related`)

                for (let i = 0; i < relations.length; i++) {

                    const type = relations[i].relationship_type;
                    const resource1 = relations[i].resource;
                    const resource2 = relations[i].related_to_resource;
                    
                    let theOtherResourceId = resource1;
                    if (resourceId == theOtherResourceId) {
                        theOtherResourceId = resource2;
                    } 

                    const theOtherResource = resources[theOtherResourceId];
                    
                    const link = `${config.org_id ? '/' + config.org_id: ''}/resources/${theOtherResourceId}`;
                    const relatedResourceElement = `<a class="tag is-white is-underlined pl-0" href="${link}">${theOtherResource.title}</a>`

                    switch (type) {
                        case 'is-related':
                            $(`.box-${resourceId} .is-related-label`).removeClass('is-hidden')
                            isRelated.append(relatedResourceElement);
                            break;
                        case 'is-child':
                            if (resourceId == resource1) {
                                $(`.box-${resourceId} .has-parents-label`).removeClass('is-hidden')
                                hasParents.append(relatedResourceElement);
                            } else {
                                $(`.box-${resourceId} .has-children-label`).removeClass('is-hidden')
                                hasChildren.append(relatedResourceElement);
                            }
                            break;
                        case 'is-parent':
                            if (resourceId == resource1) {
                                $(`.box-${resourceId} .has-children-label`).removeClass('is-hidden')
                                hasChildren.append(relatedResourceElement);
                            } else {
                                $(`.box-${resourceId} .has-parents-label`).removeClass('is-hidden')
                                hasParents.append(relatedResourceElement);
                            }
                            break;
                    }
                }
            })
        }
    }
}

// Adapted from https://bulma.io/documentation/components/modal/
document.addEventListener('DOMContentLoaded', () => {
    // Functions to open and close a modal
    function openModal($el, $trigger) {
        // If modal is opened via an info-circle, show only the respective info part
        if ($trigger.hasAttribute('data-target-value')) {
            (document.querySelectorAll('.single-access-info') || []).forEach(($info) => {
                $info.style.display = "none";
            });

            const infoBox =  document.querySelector('div[data-value="' + $trigger.getAttribute('data-target-value') + '"]');
            if (infoBox) {
                infoBox.style.display = "initial";
            }
        } else {
            (document.querySelectorAll('.single-access-info') || []).forEach(($info) => {
                $info.style.display = "initial";
            });
        }

        $el.classList.add('is-active');
    }

    function closeModal($el) {
        $el.classList.remove('is-active');
    }

    function closeAllModals() {
        (document.querySelectorAll('.modal') || []).forEach(($modal) => {
            closeModal($modal);
        });
    }

    // Add a click event on buttons to open a specific modal
    (document.querySelectorAll('.js-modal-trigger') || []).forEach(($trigger) => {
        const modal = $trigger.dataset.target;
        const $target = document.getElementById(modal);

        $trigger.addEventListener('click', () => {
            openModal($target, $trigger);
        });
    });

    // Add a click event on various child elements to close the parent modal
    (document.querySelectorAll('.modal-background, .modal-close, .modal-card-head .delete, .modal-card-foot .button') || []).forEach(($close) => {
        const $target = $close.closest('.modal');

        $close.addEventListener('click', () => {
            closeModal($target);
        });
    });

    // Add a keyboard event to close all modals
    document.addEventListener('keydown', (event) => {
        const e = event || window.event;

        if (e.keyCode === 27) { // Escape key
            closeAllModals();
        }
    });
});