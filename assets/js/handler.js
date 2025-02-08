document.addEventListener('DOMContentLoaded', () => {
    console.log('Octanist handler.js loaded');

    const getCookies = () => {
        const cookies = {};
        document.cookie.split(';').forEach(cookie => {
            const [name, value] = cookie.split('=').map(c => c.trim());
            if (name && value) {
                cookies[name] = decodeURIComponent(value);
            }
        });
        return cookies;
    };

    const getFieldMappings = () => {
        if (typeof octanistSettings !== 'undefined' && typeof octanistSettings.fieldMappings === 'object') {
            return octanistSettings.fieldMappings;
        } else {
            console.warn('Field mappings are not an object:', octanistSettings.fieldMappings);
            return {};
        }
    };

    const processFieldMappings = (mappings) => {
        const processedMappings = {};

        Object.keys(mappings).forEach(key => {
            const value = mappings[key];

            if (value) {
                processedMappings[value] = key;
            }
        });

        return processedMappings;
    };

    const mapFormFields = (form, mappings) => {
        const formData = new FormData(form);
        const mappedData = {};

        formData.forEach((value, key) => {
            const mappedKey = mappings[key] || key;
            mappedData[mappedKey] = value;
        });

        return mappedData;
    };


    const appendOctanistIdToForm = (form) => {
        if (typeof octanistSettings !== 'undefined' && octanistSettings.octanistID) {
            const octanistInput = document.createElement('input');
            octanistInput.type = 'hidden';
            octanistInput.name = 'octanist_id';
            octanistInput.value = octanistSettings.octanistID;
            form.appendChild(octanistInput);
        }
    };

    const checkRequiredFields = (form) => {
        const requiredElements = form.querySelectorAll('[required], [aria-required="true"], [required="true"]');

        // Laat ff staan voor debugging
        console.log('Required elements:', requiredElements);

        for (const element of requiredElements) {
            if (!element.value.trim()) {

                console.error(`Missing required field: ${element.name || element.id}`);
                return false;
            }
        }
        return true;
    };


    const sendDataToEndpoint = async (data) => {
        if (typeof octanistSettings !== 'undefined' && octanistSettings.octanistID) {
            try {
                const url = `https://octanist.com/api/integrations/incoming/wp/${octanistSettings.octanistID}/`;

                //console.log(url);

                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data),
                });

                if (!response.ok) {
                    console.error('Failed to send data:', response.statusText);
                } else {
                    console.log('Data successfully sent to Octanist endpoint');
                }
            } catch (error) {
                console.error('Error sending data:', error);
            }
        } else {
            console.error('No Octanist ID found in settings');
        }
    };

    const formsWithFormClass = Array.from(document.querySelectorAll('form.wpcf7-form, .wpcf7-form, .octanist-form, .frm-fluent-form'));

    const allForms = [
        ...formsWithFormClass
    ].filter(form => form !== null);

    //console.log('All forms:', allForms);

    let cookies;
    try {
        cookies = getCookies();
    } catch (error) {
        console.error('Error getting cookies:', error);
    }

    let rawMappings;
    try {
        rawMappings = getFieldMappings();
    } catch (error) {
        console.error('Error getting field mappings:', error);
    }

    let fieldMappings;
    try {
        fieldMappings = processFieldMappings(rawMappings);
    } catch (error) {
        console.error('Error processing field mappings:', error);
    }

    allForms.forEach(form => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();

            appendOctanistIdToForm(form);

            let mappedData;
            try {
                mappedData = mapFormFields(form, fieldMappings);

                if (!checkRequiredFields(form)) {
                    return;
                }

                if (!mappedData.name || typeof mappedData.name !== 'string') {
                    throw new Error('Invalid name');
                }
                if (!mappedData.email || !validateEmail(mappedData.email)) {
                    console.log('Invalid email:', mappedData.email);
                    throw new Error('Invalid email');
                }
                // if (!mappedData.phone || !validatePhone(mappedData.phone)) {
                //     throw new Error('Invalid phone');
                // }

                mappedData.cookies = cookies;
                mappedData.domain = window.location.hostname;
                mappedData.path = window.location.pathname;

            } catch (error) {
                console.error('Error mapping form fields:', error);
                return;
            }

            console.log('Verzamelde data:', mappedData);

            try {
                sendDataToEndpoint(mappedData);
            } catch (error) {
                console.error('Error sending data to endpoint:', error);
            }
        });
    });
});

function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(String(email).toLowerCase());
}

// function validatePhone(phone) {
//     const re = /^\+?[1-9]\d{1,14}$/;
//     return re.test(String(phone));
// }