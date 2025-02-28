// Accordion.js
// 
// Simple library for accordion-like GUI.
//


function onItemAddedToDOM(item)
{
    if (item.classList && item.classList.contains("accordion")) {
        initAccordion(item, true);
    }
}

function initAccordion(accordion, isKeepingOpen=false) {
    // Initially hide accordion
    if(!isKeepingOpen)
    {
        accordion.classList.remove("is-active");  
    } else {
        accordion.classList.add("is-active");          
    }
    accordion.querySelector(".accordion-header button").addEventListener("click", (evt) => {
        evt.preventDefault();
        accordion.classList.toggle("is-active");
        evt.target.setAttribute('aria-expanded', accordion.classList.contains("is-active"));
    });
}

document.addEventListener("DOMContentLoaded", function (event) {
    const accordions = document.querySelectorAll(".accordion");
    const observer = new MutationObserver(function (mutations) {
        mutations.forEach(m => {
            m.addedNodes.forEach(n => {
                if (document.contains(n)) {
                    onItemAddedToDOM(n);
                }
            });
        });
    });

    observer.observe(document, {
        attributes: false,
        childList: true,
        characterData: true,
        subtree: true
    });

    accordions.forEach((accordion) => {
        initAccordion(accordion);
    });
});