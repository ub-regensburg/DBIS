import './scss/users_base.scss';
import '@fortawesome/fontawesome-free/js/all.js';

//
//
// DROPDOWN BEHAVIOR
export function addDropdownInteractivity(dropdownDiv)
{
    const trigger = dropdownDiv.querySelector('.dropdown-trigger button');
    const searchfield = dropdownDiv.querySelector('input.searchfield');
    const menuItems = dropdownDiv.querySelectorAll('.dropdown-items button');
    const cities = dropdownDiv.querySelectorAll('p.city');

    searchfield.setAttribute("aria-label", "Suchfeld für die Facettierung der Suchergebnisse");
    
    // on direct click, open dropdown    
    trigger.onclick = (evt) => {
        evt.stopPropagation();
        dropdownDiv.classList.toggle('is-active');
        if(dropdownDiv.classList.contains('is-active') && 
                dropdownDiv.classList.contains('searchable')) {
            searchfield.focus();
        }
    };
    
    searchfield.onclick = (evt) => {
        evt.stopPropagation();
    }
    
    // if click is beyond element, close the dropdown
    document.addEventListener("click", (event) => {
        dropdownDiv.classList.remove("is-active");
    });
    
    // add behavior for showing and hiding items
    if(dropdownDiv.classList.contains('searchable')) {
        searchfield.onkeyup = (evt) => {
            const term = evt.target.value.toLowerCase(); 

            menuItems.forEach(item => {
                // Iterate each organisation <button>

                const itemName = item.innerHTML.toLowerCase();
                let itemValue = "";
                if (item.value) {
                    itemValue = item.value.toLowerCase();
                }

                let cityName = ""
                if (item.hasAttribute('data-city')) {
                    cityName = item.getAttribute('data-city').toLowerCase();
                }

                if (itemName.includes(term) || (itemValue.length > 0 && itemValue.includes(term)) || (cityName.length > 0 && cityName.includes(term))) 
                {
                    item.style.display = "block";
                } else {
                    item.style.display = "none";
                }
            });
            
            cities.forEach(city => {
                // Iterate each city label <p>
                
                city.style.display = "none";

                let buttonElement = city.nextSibling;
                do {
                    if (buttonElement && buttonElement.style) {
                        if (buttonElement.style.display === "block") {
                            city.style.display = "block";
                            break;
                        } else {
                            city.style.display = "none";
                        }
                    } else {
                        city.style.display = "none";
                    }
                    
                } while (buttonElement = buttonElement.nextSibling);
            });
        };
    }
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

        if (arrowUp && arrowDown) {
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
