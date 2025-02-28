import {Search} from './search';

class OfflineSearchableMultiselect 
{
    constructor(root)
    {
        this._findElements(root);
        
        this._initializeRenderer();
        
        this._registerCallbacks();
        
        this.update();
    }

    _findElements(root) 
    {        
        this.root = root;
        this.search = root.querySelector(".search-field");
        // we "abuse" the multiselect object as model and simply replace the
        // content, that the users see
        this.modelOptions = Array.from(root.querySelectorAll("option"));
        this.select = root.querySelector("select");
    }
    
    _registerCallbacks()
    {        
        this.search.onkeyup = (event) =>  this.update(event.target.value);
        this.onselect = null;        
        this.select.onchange = (event) => {
            if (this.onselect instanceof Function) {
                this.onselect(event);
            }
            this.update();
        };   
    }
    
    // We are replacing the select element
    _initializeRenderer()
    {        
        // rendered fields
        this.optionsContainer = this.root.insertBefore(document.createElement("ul"), this.select);
        this.optionsContainer.classList.add("p-2");
        this.optionsContainer.classList.add("offline-searchable-multiselect");
        this.select.classList.add("hidden");
        // this.select.setAttribute("aria-hidden", "true");
    }
    
    //
    //
    //
    
    update(q=null)
    {
        if(!q)
            q = this.search.value;
        this.modelOptions.forEach(opt => {
            const id = opt.value;        
            const optionVal = opt.innerHTML;
            let isHidden = !Search.matchStrings(q, optionVal);
            // isHidden = isHidden || opt.selected;
            // isHidden = isHidden && !opt.selected;
            opt.classList.toggle("hidden", isHidden);
        });     
        this.render();
    }
    
    render()
    {
        // clear the container
        this.optionsContainer.innerHTML = "";
        // transform options in model to items
        this.modelOptions.forEach(opt => {
            const id = opt.value;        
            const optionTitle = opt.innerHTML;
            let isHidden = opt.classList.contains("hidden");
            
            if(!isHidden)
            {
                this._renderItem({
                    id: id,
                    title: optionTitle,
                    checked: opt.selected,
                    disabled: opt.disabled
                });                
            }
        });        
    }
    
    _renderItem(data)
    {
        const item = this.optionsContainer.appendChild(document.createElement("li"));
        const label = item.appendChild(document.createElement("label"));
        label.innerHTML = data.title;
        label.classList.add("checkbox");
        if (data.disabled){
            label.classList.add("disabled");
        }
        const input = label.insertBefore(document.createElement("input"), label.firstChild);
        input.type = "checkbox";
        input.dataset.id = data.id;
        input.checked = data.checked;
        input.disabled = data.disabled;
        input.classList.add("mr-1");
        
        input.onchange = (event) => {
            const id = event.target.dataset.id;
            const state = event.target.checked;
            this._onSelection(id, state)
        }
        
        item.classList.add("pb-2");
        item.classList.add("mb-2");
    }
    
    _onSelection(id, isSelected)
    {
        const modelOption = this.modelOptions.find(item => item.value === id);
        modelOption.selected = isSelected;
        this.select.onchange();
        this.update();        
    }
    
    
    
    getSelectedOptions()
    {        
        return this.modelOptions.filter(item => item.selected);        
    }
    
    deselectOption(id)
    {
        this.root.querySelector("option[value='" + id +"']").selected = false;
        this.update();                
    }
    
    
    
    reset()
    {
        this.search.value = "";
        
    }
}

export {
    OfflineSearchableMultiselect
}