document.addEventListener("DOMContentLoaded", () => {
  const octanistFormHandler = {
    init() {
      this.settings = this.getSettings();
      if (!this.settings) {
        this.log("Octanist settings not found.", "error");
        return;
      }

      // Only proceed if we have an Octanist ID
      if (!this.settings.octanistID) {
        this.log("Octanist ID not configured.", "warn");
        return;
      }

      this.log("Octanist Universal handler.js loaded");

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
      if (typeof octanistSettings === "undefined") {
        console.warn("Octanist: Settings not loaded properly.");
        return null;
      }
      return octanistSettings;
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

    sendDataToEndpoint(data) {
      if (!this.settings.octanistID) {
        this.log("Cannot send data: Octanist ID not configured.", "error");
        return;
      }

      try {
        const url = `https://octanist.com/api/integrations/incoming/wp/${encodeURIComponent(this.settings.octanistID)}/`;
        const payload = JSON.stringify(data);
        const blob = new Blob([payload], {
          type: "application/json",
        });

        if (navigator.sendBeacon && navigator.sendBeacon(url, blob)) {
          this.log("Data successfully sent to Octanist endpoint via Beacon.");
        } else {
          // Fallback to fetch if sendBeacon fails
          this.sendDataWithFetch(url, payload);
        }
      } catch (error) {
        this.log(`Error sending data: ${error.message}`, "error");
      }
    },

    sendDataWithFetch(url, payload) {
      fetch(url, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: payload,
        keepalive: true,
      })
      .then(response => {
        if (response.ok) {
          this.log("Data successfully sent to Octanist endpoint via Fetch.");
        } else {
          this.log(`HTTP ${response.status}: Failed to send data`, "error");
        }
      })
      .catch(error => {
        this.log(`Fetch error: ${error.message}`, "error");
      });
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
      // Use requestIdleCallback for better performance
      const bindFormsCallback = () => {
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

        if (forms.length === 0) {
          this.log("No supported forms found on this page.", "warn");
          return;
        }

        forms.forEach((form) => {
          if (!form.dataset.octanistBound) {
            form.addEventListener("submit", this.handleSubmit.bind(this));
            form.dataset.octanistBound = "true";
          }
        });
      };

      if (window.requestIdleCallback) {
        requestIdleCallback(bindFormsCallback);
      } else {
        setTimeout(bindFormsCallback, 100);
      }
    },

    handleSubmit(event) {
      const form = event.target;
      this.log({
        message: "Form submitted, proceeding with data collection.",
        data: form,
      });

      try {
        this.appendOctanistIdToForm(form);
        const mappedData = this.mapFormFields(form);

        // Validate and sanitize data
        if (!mappedData.name || typeof mappedData.name !== "string") {
          mappedData.name = "";
        }
        if (!mappedData.email || !this.validateEmail(mappedData.email)) {
          mappedData.email = "";
        }

        // Skip if no meaningful data collected
        if (!mappedData.email && !mappedData.name && !mappedData.phone) {
          this.log("No meaningful form data to send.", "warn");
          return;
        }

        mappedData.cookies = this.cookies;
        mappedData.domain = window.location.hostname;
        mappedData.path = window.location.pathname;
        mappedData.timestamp = new Date().toISOString();

        this.log({ message: "Collected and mapped data", data: mappedData });

        if (this.settings.sendToOctanist === "1") {
          this.sendDataToEndpoint(mappedData);
        }
        if (this.settings.sendToDataLayer === "1") {
          this.sendToDataLayer(mappedData);
        }
      } catch (error) {
        this.log(`Error processing form for Octanist: ${error}`, "error");
      }
    },

    validateEmail(email) {
      if (typeof email !== "string") return false;
      const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return re.test(email.toLowerCase());
    },
  };

  // Initialize with error handling
  try {
    octanistFormHandler.init();
  } catch (error) {
    console.error("Octanist: Failed to initialize form handler:", error);
  }
});
