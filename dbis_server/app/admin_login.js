import './scss/admin_base.scss';
import './scss/pages/admin_login.scss';
import './admin_base.js'
import {validateForm} from './js/modules/validation';
class AdminLogin {
    constructor() {
        this.state = {};
        this.submitBtn = document.getElementById("submit-login");
        this.form = document.getElementById("login-form");
        this.inputLogin = document.getElementById("login");
        this.inputPassword = document.getElementById("password");
        
        this.form.addEventListener("submit", this.onSubmit.bind(this));
        this.submitBtn.addEventListener("click", this.onValidate.bind(this));
        
    }
    
    onValidate(event) {        
        validateForm();
        if(!(this.inputLogin.value && this.inputPassword.value)) {
            this.submitBtn.disabled = false;
        }
    }
    
    onSubmit(event) {
        this.submitBtn.disabled = true;
    }
}

new AdminLogin();