// Expandable.js
// 
// Simple library for creating an expanding ("show more/less") item, e.g. for 
// long text or fields with many items.
// 
// Status quo: Collapse for textual info is working just fine for now
//


const templateShowmore = `<button class='button is-inverted mt-2 expand'>${res.showmore}<i class="fas fa-chevron-down ml-2"></i></button>`;
const templateShowless = `<button class='button is-inverted mt-2 collapse'>${res.showless}<i class="fas fa-chevron-up ml-2"></i></button>`;


function onItemAddedToDOM(item)
{
    if (item.classList && item.classList.contains("expandable")) {
        initExpandable(item);
    }
}

function initExpandable(expandable) {
    // this checks, whether content should be truncated in a way, that keeps sentences
    const isKeepingSentences = expandable.classList.contains("is-keeping-sentences");
    // this checks, whether content should be truncated element-wise 
    // (e.g. only show n tags, buttons etc.)
    const isItemwise = expandable.classList.contains("is-item-wise");
    // this defines the maximum char length of the content
    const limitChars = parseInt(expandable.getAttribute("data-limit-chars")) || 500;
    // if handling item-wise, this limit the number of items, 
    // that are shown to the user
    const limitItems = parseInt(expandable.getAttribute("data-limit-items")) || 1;
    
    if(!isItemwise && expandable.innerHTML.length > limitChars)
    {
        truncateElementContent(expandable, limitChars, isKeepingSentences);
        createButtons(expandable);
    }
    
}

function truncateElementContent(element, maxChars, isKeepingSentences)
{
    let truncatedText = element.innerHTML.substring(0, maxChars);
    if(isKeepingSentences && element.innerHTML.lastIndexOf(". ") <= element.innerHTML.length-2 && element.innerHTML.lastIndexOf(". ") > 0)
    {
        truncatedText = truncatedText.substring(0, truncatedText.lastIndexOf(". ") + 1);
    }       
    element.dataset.fulltext = element.innerHTML; 
    element.dataset.truncatedtext = truncatedText;
    element.innerHTML = truncatedText;
}

function createButtons(element) {
    
    const btnContainer = new DOMParser().parseFromString("<div></div>", 'text/html')
            .firstChild.querySelector("div");
    const elemShowMore = new DOMParser().parseFromString(templateShowmore, 'text/html')
            .firstChild.querySelector("button");
    const elemShowLess = new DOMParser().parseFromString(templateShowless, 'text/html')
            .firstChild.querySelector("button");
    element.parentNode.insertBefore(btnContainer, element.nextSibling);
    
    btnContainer.appendChild(elemShowMore);
    btnContainer.appendChild(elemShowLess);
    
    // element.parentNode.insertBefore(elemShowLess, element.nextSibling);
    
    elemShowLess.classList.add("hidden");
    
    elemShowMore.onclick = onExpandButtonClicked;
    elemShowLess.onclick = onCollapseButtonClicked;
}

function onExpandButtonClicked(event)
{    
    const expandButton = event.target.closest(".expand");
    const collapseButton = expandButton.parentNode.querySelector(".collapse");
    const expandable = expandButton.parentNode.previousSibling;
    expandable.innerHTML = expandable.dataset.fulltext;
    expandButton.classList.add("hidden");
    collapseButton.classList.remove("hidden");
}

function onCollapseButtonClicked(event)
{
    const collapseButton = event.target.closest(".collapse");
    const expandable = collapseButton.parentNode.previousSibling;
    const expandButton = collapseButton.parentNode.querySelector(".expand");
    expandable.innerHTML = expandable.dataset.truncatedtext;
    collapseButton.classList.add("hidden");
    expandButton.classList.remove("hidden");    
}

document.addEventListener("DOMContentLoaded", function (event) {
    const expandables = document.querySelectorAll(".expandable");
    expandables.forEach(exp => {
        initExpandable(exp);
    });
    
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
});