document.addEventListener("DOMContentLoaded", () => {
  const octanistAjaxFormHandler = {
    init() {
      console.log("Octanist AJAX handler.js loaded");

      this.settings = this.getSettings();
      if (!this.settings) {
        console.error("Octanist settings not found.");
        return;
      }

      this.cookies = this.getCookies();
      this.fieldMappings = this.processFieldMappings(
        this.settings.fieldMappings
      );
      this.bindForms();
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
        return {};
      }
      const processedMappings = {};
      Object.keys(mappings).forEach((key) => {
        const values = mappings[key].split(",").map((item) => item.trim());
        values.forEach((value) => {
          processedMappings[value] = key;
        });
      });
      return processedMappings;
    },

    mapFormFields(form) {
      const formData = new FormData(form);
      const mappedData = {};
      formData.forEach((value, key) => {
        const mappedKey = this.fieldMappings[key] || key;
        mappedData[mappedKey] = value;
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
        await fetch(url, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(data),
          keepalive: true, // Crucial for AJAX forms
        });
      } catch (error) {
        console.error("Error sending data:", error);
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
    },

    bindForms() {
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

      document.querySelectorAll(formSelectors).forEach((form) => {
        form.addEventListener("submit", this.handleSubmit.bind(this));
      });
    },

    handleSubmit(event) {
      const form = event.target;
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

        if (this.settings.sendToOctanist === "1") {
          this.sendDataToEndpoint(mappedData);
        }
        if (this.settings.sendToDataLayer === "1") {
          this.sendToDataLayer(mappedData);
        }
      } catch (error) {
        console.error("Error processing form for Octanist:", error);
      }
    },
  };

  function validateEmail(email) {
    if (typeof email !== "string") return false;
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email.toLowerCase());
  }

  octanistAjaxFormHandler.init();
});
