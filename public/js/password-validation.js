class PasswordValidator {
    constructor(options = {}) {
        this.options = {
            passwordInput: null,
            confirmInput: null,
            currentInput: null,
            submitButton: null,
            feedbackElement: null,
            requireCurrentPassword: false,
            ...options
        };
        
        this.init();
    }
    
    init() {
        if (!this.options.passwordInput) return;
        
        this.options.passwordInput.addEventListener('input', () => this.validate());
        if (this.options.confirmInput) {
            this.options.confirmInput.addEventListener('input', () => this.validate());
        }
        if (this.options.currentInput) {
            this.options.currentInput.addEventListener('input', () => this.validate());
        }
    }
    
    validate() {
        const value = this.options.passwordInput.value;
        const confirmValue = this.options.confirmInput ? this.options.confirmInput.value : '';
        const currentValue = this.options.currentInput ? this.options.currentInput.value : '';
        
        // Length check
        this.updateRequirement('length-check', value.length >= 8);
        
        // Uppercase check
        this.updateRequirement('uppercase-check', /[A-Z]/.test(value));
        
        // Lowercase check
        this.updateRequirement('lowercase-check', /[a-z]/.test(value));
        
        // Number check
        this.updateRequirement('number-check', /[0-9]/.test(value));
        
        // Symbol check
        this.updateRequirement('symbol-check', /[!@#$%^&*(),.?":{}|<>]/.test(value));
        
        // Match check (if confirm input exists)
        if (this.options.confirmInput) {
            this.updateRequirement('match-check', value === confirmValue && value !== '');
        }
        
        // Update submit button and feedback
        this.updateSubmitState(value, confirmValue, currentValue);
    }
    
    updateRequirement(id, valid) {
        const element = document.getElementById(id);
        if (!element) return;
        
        const icon = element.querySelector('i');
        if (!icon) return;
        
        if (valid) {
            icon.classList.remove('fa-circle-xmark', 'text-danger');
            icon.classList.add('fa-circle-check', 'text-success');
            element.classList.remove('text-danger');
            element.classList.add('text-success');
        } else {
            icon.classList.remove('fa-circle-check', 'text-success');
            icon.classList.add('fa-circle-xmark', 'text-danger');
            element.classList.remove('text-success');
            element.classList.add('text-danger');
        }
    }
    
    updateSubmitState(value, confirmValue, currentValue) {
        if (!this.options.submitButton) return;
        
        const allRequirementsMet = document.querySelectorAll('.requirement i.text-success').length === 
            (this.options.confirmInput ? 6 : 5);
        
        const currentPasswordValid = this.options.requireCurrentPassword ? 
            (currentValue && currentValue.length > 0) : true;
        
        this.options.submitButton.disabled = !allRequirementsMet || !currentPasswordValid;
        
        if (this.options.feedbackElement) {
            this.options.feedbackElement.classList.toggle(
                'feedback-hidden', 
                allRequirementsMet && currentPasswordValid
            );
        }
    }
} 