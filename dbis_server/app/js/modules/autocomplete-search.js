class AutocompleteSearch
{
    constructor(root, template)
    {
        this.root = root;
        this.template = template;

        // Event callbacks
        this.onselect = null;
        this.ontypeahead = null;

        this.timeout = null;
        this.timeoutIntervalMs = 200;

        this.search = root.querySelector(".search");

        // register typeahead events
        this.search.onkeyup = (event) => {
            this.timeout = setTimeout(() => {
                this.ontypeahead(event.target.value);
            }, this.timeoutIntervalMs);

            if (event.target.value.length === 0) {
                this.dropdown.classList.toggle("hidden");
                this.clearDropdownList();
            }
        }

        this.search.onfocusout = (event) => {
            this.dropdown.classList.toggle("hidden");
            this.clearDropdownList();
        };

        this.dropdown = root.querySelector('.dropdown-list');
        this.dropdownlist = root.querySelector(".dropdown-list ul");
        this.dropdownValues = [];
    }

    /**
     * Expects items of type {id: any, title: any}
     * @param {type} entries
     * @returns {undefined}
     */
    setDropdownList(entries) {
        this.dropdown.classList.toggle("hidden", entries.length == 0);
        this.clearDropdownList();
        entries.forEach(entry => {
            const clone = document.importNode(this.template.content, true);
            const button = clone.querySelector("button");
            this.dropdownlist.appendChild(clone);
            button.value = entry.id;
            if (entry.match){
                button.innerHTML = new Option(entry.match).innerHTML;
            } else {
                button.innerHTML = new Option(entry.title).innerHTML;
            }

            if (entry.hasOwnProperty('category') && entry.hasOwnProperty('link')) {
                const newEl = document.createElement("span");
                newEl.setAttribute("class","category is-size-7");
                newEl.appendChild(document.createTextNode(` | ${entry.category}`));
                button.after(newEl);

                const newLink = document.createElement("a");
                newLink.setAttribute("class","gnd-link is-size-7");
                newLink.setAttribute("href", entry.link);
                newLink.appendChild(document.createTextNode(` | ${entry.link}`));
                button.after(newLink);
            }

            // Individual case when searching for gnd keywords
            if (entry.keyword_system && entry.keyword_system.length > 0) {
                button.dataset.keyword_system = entry.keyword_system;
            }

            if (entry.external_id && entry.external_id.length > 0) {
                button.dataset.external_id = entry.external_id;
            }

            button.onclick = this.onselect;
        });
    }

    clearDropdownList() {
        this.dropdownlist.innerHTML = "";
    }

    reset() {
        this.dropdown.classList.toggle("hidden");
        this.search.value = "";
        this.clearDropdownList();
    }

    update() {

    }
}

export {AutocompleteSearch};
