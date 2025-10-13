document.addEventListener('DOMContentLoaded', () => {
    // Select the form and all the individual steps
    const form = document.getElementById('page1Form');
    if (!form) return; // Stop if we're not on a page with this form

    const steps = Array.from(form.querySelectorAll('.form-step'));
    const nextButtons = form.querySelectorAll('.next-btn');
    let currentStep = 0;

    // Function to show the correct step and hide others
    function showStep(stepIndex) {
        steps.forEach((step, index) => {
            // Add the 'active' class to the step we want to show
            step.classList.toggle('active', index === stepIndex);
        });
    }

    // Function to move to the next step
    function goToNextStep() {
        const currentStepElement = steps[currentStep];
        const input = currentStepElement.querySelector('input, select, textarea');
        
        // Check if the current field is filled out (if required)
        if (input && input.required && !input.value) {
            alert('This field is required before you can continue.');
            return;
        }

        // Move to the next step if we're not at the end
        if (currentStep < steps.length - 1) {
            currentStep++;
            showStep(currentStep);
        }
    }

    // Add click listeners to all "Next" buttons
    nextButtons.forEach(button => {
        // We only add the listener to buttons that are not the final submit button
        if (button.type !== 'submit') {
            button.addEventListener('click', goToNextStep);
        }
    });

    // Allow pressing "Enter" to go to the next step
    form.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault(); // Stop the form from submitting
            // Trigger the click on the current step's "Next" button
            steps[currentStep].querySelector('.next-btn').click();
        }
    });

    // Handle the final submission of the form
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        // Save the data to the browser's temporary storage
        localStorage.setItem('registrationDataP1', JSON.stringify(data));
        
        // Redirect to the document upload page
        // We will create 'upload.html' in a future step
        window.location.href = 'upload.html';
    });
    
    // Show the very first question when the page loads
    showStep(currentStep);
});
