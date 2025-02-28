import './scss/admin_base.scss';
import './scss/admin_select_subject.scss';
import './admin_base.js'
import './js/modules/searchable_subject_list.js';
import {PickerList} from './js/modules/picker_list_for_subject.js';

document.addEventListener("DOMContentLoaded", function (event) {
    const entries = document.querySelectorAll(".resource-item");
    const sourceList = document.getElementById("resources-source-list");
    const targetList = document.getElementById("resources-target-list");
    const form = document.getElementById("top-resource-form");

    if (sourceList)
    {
        new PickerList(sourceList, targetList, {
            "sort_by": 1  // ALPHABETICAL_SORTING
        });
    }
});
