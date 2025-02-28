import '@fortawesome/fontawesome-free/js/all.js'
import './scss/admin_base.scss'
import './scss/pages/admin_manage_database.scss'
import './admin_base'


class ManageDatabase {
    constructor() {
        this.initPageSize();
    }

    initPageSize() {
        if (document.getElementById("pagination_size")) {
            document.getElementById("pagination_size").onchange = function () {
                document.getElementById("query-form").submit();
            };
        }
    }
}

document.addEventListener("DOMContentLoaded", function (event) {
    const manageDatabase = new ManageDatabase();
});