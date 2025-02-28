import './scss/admin_base.scss';
import '@fortawesome/fontawesome-free/js/all.js';

//
//
// DROPDOWN BEHAVIOR
// TODO: extract this to own class
function addDropdownInteractivity(dropdownDiv)
{   
    const isSearchable = dropdownDiv.classList.contains('searchable');
    const trigger = dropdownDiv.querySelector('.dropdown-trigger button');
    const menuItems = dropdownDiv.querySelectorAll('.dropdown-content a');
    const searchfield = dropdownDiv.querySelector('input.searchfield');
    
    // on direct click, open dropdown    
    if (trigger) {
        trigger.onclick = (evt) => {
            evt.stopPropagation();
            dropdownDiv.classList.toggle('is-active');
            if(dropdownDiv.classList.contains('is-active') && isSearchable) {
                searchfield.focus();
            }
        };
    }
    
    // if click is beyond element, close the dropdown
    document.addEventListener("click", (event) => {
        dropdownDiv.classList.remove("is-active");
    });
    
    document.addEventListener("keydown", (evt) => {
        if(evt.key === "Escape") {
            dropdownDiv.classList.remove("is-active");        
        }
    });
    
    // add behavior for showing and hiding items
    if(dropdownDiv.classList.contains('searchable')) {
        if (searchfield) {
            searchfield.onclick = (evt) => {
                evt.stopPropagation();
            }
            searchfield.onkeyup = (evt) => {
                const term = evt.target.value.toLowerCase(); 
                menuItems.forEach(item => {
                    const itemName = item.innerHTML.toLowerCase();
                    let itemValue = "";
                    if (item.getAttribute('href')) {
                        itemValue = item.getAttribute('href').toLowerCase();
                        // TODO: Do better ...
                        itemValue = itemValue.substring(19).replace("/", "");
                    }

                    let cityName = ""
                    if (item.hasAttribute('data-city')) {
                        cityName = item.getAttribute('data-city').toLowerCase();
                    }
    
                    if(itemName.includes(term) || (itemValue.length > 0 && itemValue.includes(term)) || (cityName.length > 0 && cityName.includes(term))) 
                    {
                        item.style.display = "block";
                    } else {
                        item.style.display = "none";
                    }
                });
            };
        }
    }
}

function initDropdownFunctionalityForAccessibility(node) {
    
}

document.addEventListener("DOMContentLoaded", function (event) {
    
    const dropdowns = document.querySelectorAll(".dropdown.searchable");

    dropdowns.forEach((dropdown) => {
        addDropdownInteractivity(dropdown);
    });
    
    document.querySelectorAll(".jsonly").forEach(item => {
        item.classList.remove("jsonly");
        item.classList.add("jsonly-enabled");
    })
    
    document.querySelectorAll(".nojs").forEach(item => {
        item.classList.add("hidden");
    })
    
    document.querySelectorAll(".filter").forEach(item => {
        const filterBody = item.querySelector(".filter-body");
        const filterActivator = item.querySelector(".filter-activator");
        const arrowUp = item.querySelector(".symbol-arrow.up");
        const arrowDown = item.querySelector(".symbol-arrow.down");
        if(item.classList.contains("keep-open"))
        {
            arrowDown.classList.add("hidden");
        } else {
            filterBody.classList.add("hidden");
            arrowUp.classList.add("hidden");            
        }
        
        filterActivator.onclick = (event) => {
            const state = filterBody.classList.toggle("hidden");
            arrowUp.classList.toggle("hidden", state);
            arrowDown.classList.toggle("hidden", !state);
        }
    });
});

// Add loading animation when searching
try {
    let mainSearchButton = document.getElementById("main-search-button");
    mainSearchButton.addEventListener("click", function() {
        mainSearchButton.classList.add("is-loading");
        let searchLoaders = document.querySelectorAll(".search-loading-element");
        searchLoaders.forEach(function (loader) {
            loader.classList.add("is-loading");
        });
    });
} catch(e) {}
