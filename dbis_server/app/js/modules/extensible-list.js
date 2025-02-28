// ExtensibleList.js
//
// Library for creating extensible list form items in HTML
//
// For examples on how to use it, have a look into admin_*_license pages, 
// the access section has been created using it.
//


document.addEventListener("DOMContentLoaded", function (event) {
    const extensibleLists = document.querySelectorAll(".extensible-list");

    extensibleLists.forEach((el) => {
        initExtensibleList(el);
    });
});

function initExtensibleList(el) {
        const addButton = el.querySelector("button.add-item");

        if (addButton) {
            const template = el.querySelector(".template");
            const removeItemButtons = el.querySelectorAll("button.extensible-list-delete-btn");
            template.style.display = "none";
        
            function onDeleteItem(evt) {
                evt.preventDefault();   
                const el = evt.target.closest(".extensible-list-item");
                el.remove();  
            }

            function addItem() {
                // id gen taken from s.o. user "doubletap"
                // https://stackoverflow.com/questions/1349404/generate-random-string-characters-in-javascript
                const id = (Math.random() + 1).toString(36).substring(7);
                const clone = template.cloneNode(true);
                clone.classList.remove("template");

                // Stupid workaround because of abstract class
                if (clone.classList.contains('template--access')) {
                    const hiddenInputId = document.createElement('input');
                    hiddenInputId.type = 'hidden';
                    hiddenInputId.name = 'access_id[]';

                    // clone.appendChild(hiddenInputId);

                    const hiddenInputAccesses = document.createElement('input');
                    hiddenInputAccesses.type = 'hidden';
                    hiddenInputAccesses.name = 'accesses[]';

                    // clone.appendChild(hiddenInputAccesses);

                    clone.classList.remove("template--access");

                    const hiddenInputIsTemplate = clone.querySelector('.is-access-template');
                    if (hiddenInputIsTemplate && hiddenInputIsTemplate.value == '1') {
                        hiddenInputIsTemplate.value = '0';
                        clone.removeChild(hiddenInputIsTemplate);

                        const hiddenInputTmp = document.createElement('input');
                        hiddenInputTmp.type = 'hidden';
                        hiddenInputTmp.name = 'is_access_template[]';
                        hiddenInputTmp.value = '0';

                        clone.appendChild(hiddenInputTmp);
                    }

                    /*
                    const isGlobalCheckbox = clone.querySelector('.is-global');
                    if (isGlobalCheckbox) {
                        isGlobalCheckbox.checked = false;
                    }
                    */
                }
                
                // append id to all input names
                // clone.querySelectorAll("input, select, textarea").forEach((item) => {item.name +="_" + id});
                
                // append hidden id field (can be used server side to identify groups)
                const namespaceInput = document.createElement("input");
                namespaceInput.name = el.dataset.namespace + "_" + id;
                namespaceInput.style.display = "none";
                clone.appendChild(namespaceInput);
                
                const deleteBtn = clone.querySelector("button.extensible-list-delete-btn");
                deleteBtn.onclick = onDeleteItem;

                clone.dataset.id = id;
                template.parentNode.insertBefore(clone, addButton);
                // onItemAddedToDOM(clone);
                clone.style.display = "block";
            }

            addButton.onclick = (evt) => {
                evt.preventDefault();
                addItem();
            };
            
            removeItemButtons.forEach((button) => {
                button.onclick = onDeleteItem;
            });  
        }
}