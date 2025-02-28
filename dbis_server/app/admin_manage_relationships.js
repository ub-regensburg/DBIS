import '@fortawesome/fontawesome-free/js/all.js'
import $ from 'jquery';
import './admin_base.js'
import './scss/admin_base.scss'
import './scss/pages/admin_manage_relationships.scss'
import {AutocompleteSearch} from "./js/modules/autocomplete-search";


class ManageRelationships {
    constructor() {
        this.fetchUrl = "/api/v1/resources";
    }

    initEvents() {
        const searchInputs = $('input.search-database');

        for (let searchInput of searchInputs) {                  
            searchInput.addEventListener("keyup", (e) => {   
                const searchInput = $(e.target);
                const q = searchInput.val();
    
                const root = searchInput.parents(".c-search-database");
                const box = root.parents(".box");
                const autocomplete_results = root.find(".autocomplete-results");
                
                if (q.length > 0) {           
                    autocomplete_results.html('');
    
                    this.fetchEntries({
                        q
                    })
                        .then(data => {
                            for (let i = 0; i < data.length; i++) {
                                const databaseId = data[i].id;
                                const databaseTitle = data[i].resource_title;
    
                                const newHTML = `${autocomplete_results.html()}<li class='search-result' data-id='${databaseId}' data-title='${databaseTitle}'>${databaseTitle} (${databaseId})</li>`;
                                autocomplete_results.html(newHTML);
                            }
                            autocomplete_results.show();
    
                            $('.search-result').on( "click", (e) => {
                                searchInput.val('');

                                const databaseId = $(e.target).data('id');
                                const databaseTitle = $(e.target).data('title');
    
                                const type = box.prop('id')

                                switch(type) {
                                    case 'search-database':
                                        $('#database-name').html(databaseTitle);
                                        $('#database-id').html(databaseId);

                                        $('input[name="database-id"]').val(databaseId);
                                        $('.selected-database').removeClass('is-hidden');

                                        $(`ul.top-databases`).empty();
                                        $(`ul.sub-databases`).empty();
                                        $(`ul.related-databases`).empty();


                                        this.fetchDefaults({
                                            'resourceId': databaseId
                                        }).then(data => {
                                            this.insertDefaults(databaseId, data);
                                        });

                                        $('#related-databases input.search-database').prop("disabled", false);
                                        $('#sub-databases input.search-database').prop("disabled", false);
                                        $('#top-databases input.search-database').prop("disabled", false);

                                        $('.manage-relationships-form button[type=submit]').prop("disabled", false);

                                        break;
                                    case 'top-databases':
                                    case 'related-databases':
                                    case 'sub-databases':
                                        const liElement = `<li class='selected-database' data-id=${databaseId}><span class='database-name'>${databaseTitle} (${databaseId})</span><input type="hidden" name="${type}[]" value="${databaseId}"/><btn class="delete delete-database ml-3"></btn></li>`;
                                        $(`ul.${type}`).append(liElement);
                                }

                                autocomplete_results.html('');
                                autocomplete_results.hide();
                            });
                        });
                } else {
                    autocomplete_results.html('');
                    autocomplete_results.hide();
                }
            });
        }

        $('.manage-relationships').on('click', '.selected-database', (e) => {
            const removeBtn = $(e.target);
            const liElement = removeBtn.parent(".selected-database");
            liElement.remove();
        });

        $(document).on('click', '.manage-relationships-form button[type=submit]', function(e) {
            /*
            const searchInput = $('#search-database input.search-database');
            const q = searchInput.val();

            if (q.length < 1) {
                e.preventDefault(); 
            }
            */
        });
    }

    insertDefaults(databaseId, data) {
        console.log(data);

        const relations = data.relations;
        const resources = data.resources;

        for (const relation of relations) {
            let resourceId = relation.resource;
            let relatedToResource = relation.related_to_resource;
            let type = relation.relationship_type;

            if (databaseId == relatedToResource) {
                switch(type) {
                    case 'is-child':
                        type = 'sub-databases';
                        break;
                    case 'is-related':
                        type = 'related-databases';
                        break;
                    case 'is-parent':
                        type = 'top-databases';
                        break;
                }
            } else {
                switch(type) {
                    case 'is-child':
                        type = 'top-databases';
                        break;
                    case 'is-related':
                        type = 'related-databases';
                        break;
                    case 'is-parent':
                        type = 'sub-databases';
                        break;
                }
            }
            
            let resourceToDisplay = relatedToResource;
            if (databaseId == relatedToResource) {
                resourceToDisplay = resourceId;
            }
            const databaseTitle = resources[resourceToDisplay].title;

            const liElement = `<li class='selected-database' data-id=${resourceToDisplay}><span class='database-name'>${databaseTitle}</span><input type="hidden" name="${type}[]" value="${resourceToDisplay}"/><btn class="delete delete-database ml-3"></btn></li>`;
            $(`ul.${type}`).append(liElement);
        }
    }

    async fetchDefaults(options = {}) {
        const fetchUrl = `/api/v1/relations/${options.resourceId}`;

        let result = null;
        let url = options.url ? new URL(options.url) : new URL(fetchUrl, document.baseURI);
        let json = null;
        if (!options.url) {
            if (options.language) {
                url.searchParams.set("language", options.language);
            }
        }

        url.searchParams.set("all", true);

        try {
            result = await fetch(url, {
                method: 'GET',
                signal: this.cancelFetchSignal,
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

    async fetchEntries(options = {}) {
        options = { ...options, ...this.options }

        let result = null;
        let url = options.url ? new URL(options.url) : new URL(this.fetchUrl, document.baseURI);
        let json = null;
        if (!options.url) {
            if (options.language) {
                url.searchParams.set("language", options.language);
            }
            if (options.organization) {
                url.searchParams.set("organization-id", options.organization);
            }
            if (options.q) {
                url.searchParams.set("q", options.q);
            }
            if (options.sort_by) {
                url.searchParams.set("sort-by", options.sort_by);
            }
        }

        url.searchParams.set("all", true);

        try {
            result = await fetch(url, {
                method: 'GET',
                signal: this.cancelFetchSignal,
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            json = await result.json();

            return json.data.resources;
        } catch (err) {
            console.log(err)
            return null
        }
    }
}

$(document).ready(function() {
    const manageRelationships = new ManageRelationships();
    manageRelationships.initEvents();
});
