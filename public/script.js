document.addEventListener('DOMContentLoaded', () => {
    // Check which page's form we are on
    if (document.getElementById('page1Form')) {
        setupPage1();
    } else if (document.getElementById('page2Form')) {
        setupPage2();
    }
});

function setupPage1() {
    const form = document.getElementById('page1Form');
    const steps = Array.from(form.querySelectorAll('.form-step'));
    const nextButtons = form.querySelectorAll('.next-btn');
    let currentStep = 0;

    function showStep(stepIndex) {
        steps.forEach((step, index) => {
            step.classList.toggle('active', index === stepIndex);
        });
    }

    function goToNextStep() {
        const currentStepElement = steps[currentStep];
        const input = currentStepElement.querySelector('input, select, textarea');
        if (input && input.required && !input.value) {
            alert('This field is required before you can continue.');
            return;
        }
        if (currentStep < steps.length - 1) {
            currentStep++;
            showStep(currentStep);
        }
    }

    nextButtons.forEach(button => {
        if (button.type !== 'submit') {
            button.addEventListener('click', goToNextStep);
        }
    });

    form.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            steps[currentStep].querySelector('.next-btn').click();
        }
    });

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        localStorage.setItem('registrationDataP1', JSON.stringify(data));
        window.location.href = 'upload.html';
    });
    
    showStep(currentStep); // Show the first step on page load
}

function setupPage2() {
    const form = document.getElementById('page2Form');
    const steps = Array.from(form.querySelectorAll('.form-step'));
    const nextButtons = form.querySelectorAll('.next-btn');
    const casteIncomeSection = document.getElementById('casteIncomeSection');
    const finalSubmitSection = document.getElementById('finalSubmitSection');
    const casteIncomeInput = document.getElementById('caste_income');
    const submitStatus = document.getElementById('submit-status');
    let currentStep = 0;

    // Get data from the first page
    const page1Data = JSON.parse(localStorage.getItem('registrationDataP1'));
    if (!page1Data) {
        window.location.href = 'index.html'; // Go back if no data
        return;
    }

    // Conditionally handle the caste certificate step
    const needsCasteCert = page1Data.category && page1Data.category !== 'NOT APPLICABLE';
    if (needsCasteCert) {
        casteIncomeInput.required = true;
        finalSubmitSection.remove(); // Remove the alternate final step
    } else {
        casteIncomeSection.remove(); // Remove the caste/income step
    }
    
    const visibleSteps = Array.from(form.querySelectorAll('.form-step')); // Recalculate steps after removing one

    function showStep(stepIndex) {
        visibleSteps.forEach((step, index) => {
            step.classList.toggle('active', index === stepIndex);
        });
    }

    function goToNextStep() {
        const currentStepElement = visibleSteps[currentStep];
        const input = currentStepElement.querySelector('input');
        if (input && input.required && !input.value) {
            alert('This field is required before you can continue.');
            return;
        }
        if (currentStep < visibleSteps.length - 1) {
            currentStep++;
            showStep(currentStep);
        }
    }

    nextButtons.forEach(button => {
        if (button.type !== 'submit') {
            button.addEventListener('click', goToNextStep);
        }
    });
    
    form.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            visibleSteps[currentStep].querySelector('.next-btn').click();
        }
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitButton = form.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        submitStatus.textContent = 'Submitting, please wait... This may take a moment.';

        const finalFormData = new FormData();
        for (const key in page1Data) {
            finalFormData.append(key, page1Data[key]);
        }
        
        const page2FileInputs = form.querySelectorAll('input[type="file"]');
        page2FileInputs.forEach(input => {
            if (input.files[0]) {
                finalFormData.append(input.name, input.files[0]);
            }
        });

        try {
            const response = await fetch('/api/submit', {
                method: 'POST',
                body: finalFormData
            });
            const result = await response.json();

            if (!response.ok) throw new Error(result.error || 'Submission failed.');
            
            localStorage.removeItem('registrationDataP1');
            alert(`Submission successful! Your Admission ID is: ${result.studentId}`);
            
            // Redirect to WhatsApp
            const whatsappMessage = `Thank you for registering. Your Admission ID is ${result.studentId}.`;
            const studentPhone = page1Data.mobile_number.replace(/\D/g, '');
            window.location.href = `https://wa.me/91${studentPhone}?text=${encodeURIComponent(whatsappMessage)}`;

        } catch (error) {
            submitStatus.textContent = `Error: ${error.message}`;
            submitButton.disabled = false;
        }
    });

    showStep(currentStep); // Show the first step on page load
}
