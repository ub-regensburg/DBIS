/**
 * PickerList
 *
 * Behavior script for creating two column picker, where left column contains
 * selected items and right column contains available items.
 */
import $ from 'jquery';
import './picker_list_for_subject.scss';

class PickerList
{
    static NUM_ITEMS = 20;

    constructor(sourceList, targetList, options = {})
    {
        this.sourceList = sourceList;
        this.targetList = targetList;
        this.loadMoreButton = this.sourceList.parentNode.querySelector(".load-more-button");
        this.searchField = this.sourceList.parentNode.parentNode.querySelector(".pickable-search-input");
        this.searchButton = this.sourceList.parentNode.parentNode.querySelector(".pickable-search-button");
        this.resources = [];
        this.fetchUrl = "/api/v1/resources";
        this.entryTemplate = sourceList.querySelector(".template");
        this.orgId = window.config.org_id;
        this.subject = sourceList.dataset.subjectId;
        this.isCollection = sourceList.dataset.isCollection === "true";
        this.options = options;

        this._initTargetList();

        this.updateGUI();

        this.loadItems().then(() => {
            this.initListeners();
            this.updateNextButton();
            this.updateGUI();
        });

    }

    _initTargetList()
    {
        this.entries = Array.from(this.targetList.querySelectorAll(".pickable-item")).map(item => {
            const entry = new PickerListEntry(item);
            entry.setPicked(true);
            return entry;
        });
    }

    initListeners() {
        document.addEventListener("moveup", (evt) => {
            this.onMoveItemUp(evt.detail.controller);
        });
        document.addEventListener("movedown", (evt) => {
            this.onMoveItemDown(evt.detail.controller);
        });
        document.addEventListener("pick", (evt) => {
            this.onPickItem(evt.detail.controller);
        });
        document.addEventListener("unpick", (evt) => {
            this.onUnpickItem(evt.detail.controller);
        });
        this.loadMoreButton.addEventListener("click", function() {
            this.loadMoreItems();
            this.loadMoreButton.classList.add("is-loading");
        }.bind(this));

        this.searchButton.addEventListener("click", () => {
            this.loadItems(this.searchField.value);
        });
    }

    async loadItems(q=null) {
        const sortBy = q && q.length > 0 ? 0 : this.options.sort_by

        const selectedIds = this._getPickedIds();

        let fetchResults = null;

        if (this.isCollection) {
            fetchResults = await this.fetchEntries({
                "language": window.config.lang,
                "organization": window.config.org_id,
                "collection": this.subject,
                "q": q,
                "sort_by": sortBy
            });
        } else {
            fetchResults = await this.fetchEntries({
                "language": window.config.lang,
                "organization": window.config.org_id,
                "subject": this.subject,
                "q": q,
                "sort_by": sortBy
            });
        }

        if (fetchResults == null) {
            return;
        }

        this.clearSourceList();
        this.resources = fetchResults.data.resources;
        this.totalNr = fetchResults.data.total;
        this.currentIndex = this.resources.length;
        this.nextURL = fetchResults['links']['next'];
        // Filter out all items, that already have been picked
        this.resources = this.resources.filter(item => {
            return !selectedIds.includes(item.id);
        });
        this.resources.forEach(resource => {
            this.createEntry(resource);
        });
        while(this.resources.length < PickerList.NUM_ITEMS && this.nextURL !== null) {
            await this.loadMoreItems();
        }
        this.updateNextButton();
    }

    _getPickedIds() {
        return Array.from(this.targetList.querySelectorAll("input[name='resources[]']"))
                .map(object => parseInt(object.value));
    }

    clearSourceList () {
        this.resources = [];
        this.entries = [];
        this.nextURL = undefined;
        this.sourceList.querySelectorAll('.pickable-item:not(.template)').forEach(node => node.remove());
        this.updateNextButton();
        // We need to re-init the target list, sin
        this._initTargetList();
    }

    async loadMoreItems() {
        const selectedIds = Array.from(
                this.targetList.querySelectorAll("input[name='resources[]']"))
                .map(object => parseInt(object.value));
        this.loadMoreButton.disabled = "true";

        const newItems = await this.fetchEntries({
            url: this.nextURL
        });

        const newResources = newItems['data']['resources'].filter(item => {
            return !selectedIds.includes(item.id);
        });

        newResources.forEach(r => {
           this.createEntry(r);
        });
        this.resources = this.resources.concat(newResources);

        this.loadMoreButton.disabled = "";
        this.nextURL = newItems['links']['next'];
        this.updateNextButton();
    }

    updateNextButton() {
        const button = this.sourceList.parentNode.querySelector(".load-more-button");
	    button.classList.remove("is-loading");
        if(this.nextURL !== null) {
            // this.sourceList.appendChild(button);
            button.classList.toggle("is-hidden", !this.nextURL);
        } else {
            // Hide the load more button if there is no url
            button.classList.toggle("is-hidden", true);
        }
    }

    createEntry(resourceData) {
        const templateClone = this.entryTemplate.cloneNode(true);
        let item = undefined;
        // Make node visible and unmark as tempalte
        templateClone.classList.remove("template");
        templateClone.style.display = "";
        templateClone.setAttribute('aria-hidden', 'false');
        // Set values
        templateClone.id = "pickable_" + resourceData.id;
        this.entryTemplate.parentNode.appendChild(templateClone);
        item = new PickerListEntry(templateClone);
        item.bindData(resourceData.id, resourceData.title);
        this.entries.push(item);
    }

    async fetchEntries(options = {}) {
        options = { ...this.options, ...options }

        let result = null;
        let url = options.url ? new URL(options.url) : new URL(this.fetchUrl, document.baseURI);
        let json = null;
        if(!options.url) {
            if(options.language) {
                url.searchParams.set("language", options.language);
            }
            if(options.organization) {
                url.searchParams.set("organization-id", options.organization);
            }
            if(options.subject) {
                url.searchParams.set("subjects[]", options.subject);
            }
            if(options.collection) {
                url.searchParams.set("collections[]", options.collection);
            }
            if(options.q) {
                url.searchParams.set("q", options.q);
            }
            if(options.sort_by) {
                url.searchParams.set("sort-by", options.sort_by);
            }
        }

        // To prevent cert error
        // url.protocol = "http:";
        // url.host = "localhost:8080";

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


    onPickItem(itemController) {
        const node = itemController.rootNode;
        this.targetList.appendChild(node);
        itemController.setPicked(true);
        this.updateGUI();
    }

    onUnpickItem(itemController) {
        const node = itemController.rootNode;
        node.classList.remove("search-ignore");
        // TODO: Append node before load more button (if exists/ is visible)
        this.sourceList.appendChild(node);
        itemController.setPicked(false);
        this.updateGUI();
    }

    onMoveItemUp(itemController) {
        const node = itemController.rootNode;
        if (node.previousElementSibling) {
            node.parentNode.insertBefore(node, node.previousElementSibling);
        }
        this.updateGUI();
    }

    onMoveItemDown(itemController) {
        const node = itemController.rootNode;
        if (node.nextElementSibling && node.nextElementSibling.nextElementSibling) {
            node.parentNode.insertBefore(node, node.nextElementSibling.nextElementSibling);
        } else {
            node.parentNode.appendChild(node);
        }
        this.updateGUI();
    }

    updateGUI() {
        this.entries.forEach(e => e.update());
    }

}

class PickerListEntry
{
    constructor(rootNode)
    {
        this.rootNode = rootNode;
        this.id = rootNode.id;
        this.isPicked = false;

        this._initComponents();
        this._initListeners();

        this.update();
    }

    bindData(id, title)
    {
        this.label.innerHTML = title;
        this.idField.value = id;
        this.labelId.innerHTML = id;
    }

    _initComponents()
    {
        this.upButton = this.rootNode.querySelector(".move-up-button");
        this.downButton = this.rootNode.querySelector(".move-down-button");
        this.pickButton = this.rootNode.querySelector(".pick-button");
        this.unpickButton = this.rootNode.querySelector(".unpick-button");

        this.idField = this.rootNode.querySelector('input[name="resources[]"]');
        this.label = this.rootNode.querySelector(".pickable-title");
        this.labelId = this.rootNode.querySelector(".pickable-id");
    }

    _initListeners()
    {
        // We need to bind "this", since event handlers would lose the context
        // and handler functions could not access "this"
        // this.upButton.addEventListener("click", this.moveUp.bind(this));
        $(this.upButton).off();
        $(this.upButton).on( "click", (e) => {
            this.moveUp();
        });
        $(this.downButton).off();
        $(this.downButton).on( "click", (e) => {
            this.moveDown();
        });
        // this.downButton.addEventListener("click", this.moveDown.bind(this));
        this.pickButton.addEventListener("click", this.pick.bind(this));
        this.unpickButton.addEventListener("click", this.unpick.bind(this));
    }


    setPicked(isPicked)
    {
        this.isPicked = isPicked;
        this.update();
    }

    pick()
    {
        const event = new CustomEvent('pick', {
            bubbles: true,
            detail: {
                controller: this
            }
        });
        this.rootNode.dispatchEvent(event);
    }

    unpick()
    {
        const event = new CustomEvent('unpick', {
            bubbles: true,
            detail: {
                controller: this
            }
        });
        this.rootNode.dispatchEvent(event);
    }

    moveUp()
    {
        const event = new CustomEvent('moveup', {
            bubbles: true,
            detail: {
                controller: this
            }
        });
        this.rootNode.dispatchEvent(event);
    }

    moveDown()
    {
        const event = new CustomEvent('movedown', {
            bubbles: true,
            detail: {
                controller: this
            }
        });
        this.rootNode.dispatchEvent(event);
    }

    destroy()
    {
        const event = new Event('destroy', {
            bubbles: true });
        this.rootNode.dispatchEvent(event);
    }

    update()
    {
        this._setButtonActive(this.upButton, this.isPicked && this._hasPreviousItem());
        this._setButtonActive(this.downButton, this.isPicked && this._hasNextItem());
        
        this._setButtonActive(this.pickButton, !this.isPicked);
        this._setButtonActive(this.unpickButton, this.isPicked);
        this.idField.disabled = this.isPicked ? "" : "true";
    }

    _hasNextItem()
    {
        return this.rootNode.nextElementSibling !== null;

    }

    _hasPreviousItem()
    {
        return this.rootNode.previousElementSibling !== null;
    }

    _setButtonActive(button, isActive)
    {
        button.style.display = isActive ? "": "none";
        button.ariaHidden = !isActive;
    }
}

export {PickerList};
