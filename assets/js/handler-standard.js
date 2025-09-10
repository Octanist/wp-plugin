document.addEventListener("DOMContentLoaded", () => {
  const octanistStandardFormHandler = {
    init() {
      this.settings = this.getSettings();
      if (!this.settings) {
        this.log("Octanist settings not found.", "error");
        return;
      }

      this.log("Octanist STANDARD handler.js loaded");

      this.cookies = this.getCookies();
      this.fieldMappings = this.processFieldMappings(
        this.settings.fieldMappings
      );
      this.bindForms();
    },

    log(message, type = "log") {
      if (this.settings.debugMode !== "1") return;

      const prefix = "Octanist Debug:";
      if (type === "error") {
        console.error(prefix, message);
      } else if (type === "warn") {
        console.warn(prefix, message);
      } else {
        console.log(prefix, message);
      }
    },

    getSettings() {
      return typeof octanistSettings !== "undefined" ? octanistSettings : null;
    },

    getCookies() {
      const cookies = {};
      document.cookie.split(";").forEach((cookie) => {
        const [name, value] = cookie.split("=").map((c) => c.trim());
        if (name && value) {
          cookies[name] = decodeURIComponent(value);
        }
      });
      return cookies;
    },

    processFieldMappings(mappings) {
      if (typeof mappings !== "object" || mappings === null) {
        this.log("Field mappings are not an object:", "warn");
        return {};
      }
      const processedMappings = {};
      for (const standardField in mappings) {
        if (Array.isArray(mappings[standardField])) {
          mappings[standardField].forEach((customField) => {
            if (customField) {
              processedMappings[customField] = standardField;
            }
          });
        }
      }
      this.log({
        message: "Processed field mappings",
        data: processedMappings,
      });
      return processedMappings;
    },

    mapFormFields(form) {
      const formData = new FormData(form);
      const mappedData = {};
      formData.forEach((value, key) => {
        const mappedKey = this.fieldMappings[key] || key;
        if (mappedData.hasOwnProperty(mappedKey)) {
          mappedData[mappedKey] += " | " + value; // Concatenate with a pipe
        } else {
          mappedData[mappedKey] = value;
        }
      });
      return mappedData;
    },

    appendOctanistIdToForm(form) {
      if (this.settings.octanistID) {
        const octanistInput = document.createElement("input");
        octanistInput.type = "hidden";
        octanistInput.name = "octanist_id";
        octanistInput.value = this.settings.octanistID;
        form.appendChild(octanistInput);
      }
    },

    async sendDataToEndpoint(data) {
      if (!this.settings.octanistID) return;
      try {
        const url = `https://octanist.com/api/integrations/incoming/wp/${this.settings.octanistID}/`;
        const response = await fetch(url, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(data),
        });
        if (response.ok) {
          this.log("Data successfully sent to Octanist endpoint.");
        } else {
          this.log(`Failed to send data: ${response.statusText}`, "error");
        }
      } catch (error) {
        this.log(`Error sending data: ${error}`, "error");
      }
    },

    sendToDataLayer(data) {
      window.dataLayer = window.dataLayer || [];
      window.dataLayer.push({
        event: "submit_lead_form",
        user_data: {
          email: data.email,
          phone_number: data.phone,
          company_name: data.name,
          custom: data.custom,
        },
      });
      this.log({ message: "Sent data to dataLayer", data: data });
    },

    bindForms() {
      // This version is specifically for non-AJAX forms like Clio/Gravity Forms
      const formSelectors = [
        "form.wpcf7-form",
        ".wpcf7-form",
        ".octanist-form",
        ".frm-fluent-form",
        "#lf_form_container form",
        ".elementor-form",
        ".wpforms-form",
        ".forminator-ui",
        ".frm-show-form",
        ".nf-form-layout > form",
        'form[id*="gform_"]',
      ].join(", ");
      const forms = document.querySelectorAll(formSelectors);
      this.log({
        message: `Found ${forms.length} forms to track.`,
        data: forms,
      });
      forms.forEach((form) => {
        form.addEventListener("submit", this.handleSubmit.bind(this));
      });
    },

    async handleSubmit(event) {
      event.preventDefault(); // Prevent the form from submitting immediately
      const form = event.target;
      this.log({
        message: "Form submitted, preventing default action.",
        data: form,
      });

      try {
        this.appendOctanistIdToForm(form);
        const mappedData = this.mapFormFields(form);

        if (!mappedData.name || typeof mappedData.name !== "string")
          mappedData.name = "";
        if (!mappedData.email || !validateEmail(mappedData.email))
          mappedData.email = "";

        mappedData.cookies = this.cookies;
        mappedData.domain = window.location.hostname;
        mappedData.path = window.location.pathname;

        this.log({ message: "Collected and mapped data", data: mappedData });

        const tasks = [];
        if (this.settings.sendToOctanist === "1") {
          tasks.push(this.sendDataToEndpoint(mappedData));
        }
        if (this.settings.sendToDataLayer === "1") {
          this.sendToDataLayer(mappedData);
        }

        if (tasks.length > 0) {
          await Promise.all(tasks);
        }
      } catch (error) {
        this.log(`Error processing form for Octanist: ${error}`, "error");
      } finally {
        this.log("Resubmitting form now.");
        form.submit(); // Resubmit the form
      }
    },
  };

  function validateEmail(email) {
    if (typeof email !== "string") return false;
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email.toLowerCase());
  }

  octanistStandardFormHandler.init();
});
