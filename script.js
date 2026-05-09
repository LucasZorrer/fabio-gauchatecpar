const header = document.querySelector("[data-header]");
const menuToggle = document.querySelector("[data-menu-toggle]");
const nav = document.querySelector("[data-nav]");
const contactForm = document.querySelector("[data-contact-form]");
const formNote = document.querySelector("[data-form-note]");
const initialContactStatus = new URLSearchParams(window.location.search).get("contato");
let contactRecipient = "ri@gauchatecpar.com.br";

const getContentValue = (content, path) => {
  return path.split(".").reduce((current, key) => {
    if (current === undefined || current === null) {
      return undefined;
    }

    return current[key];
  }, content);
};

const escapeHtml = (value) => {
  const div = document.createElement("div");
  div.textContent = value;
  return div.innerHTML;
};

const looksLikeUrl = (value) => /^https?:\/\//i.test(String(value || "").trim());

const setFormMessage = (message, type = "") => {
  if (!formNote) {
    return;
  }

  formNote.textContent = message;
  formNote.classList.toggle("is-success", type === "success");
  formNote.classList.toggle("is-error", type === "error");
};

const applyInitialContactStatus = () => {
  if (initialContactStatus === "enviado") {
    setFormMessage("Mensagem enviada com sucesso.", "success");
  }

  if (initialContactStatus === "erro") {
    setFormMessage("Não foi possível enviar agora. Tente novamente ou use os contatos diretos ao lado.", "error");
  }
};

const applySiteContent = (content) => {
  document.querySelectorAll("[data-content]").forEach((element) => {
    const value = getContentValue(content, element.dataset.content);

    if (typeof value !== "string") {
      return;
    }

    if (element.dataset.contentFormat === "lines") {
      element.innerHTML = escapeHtml(value).replace(/\n/g, "<br>");
      return;
    }

    element.textContent = value;
  });

  document.querySelectorAll("[data-content-attr]").forEach((element) => {
    const mappings = element.dataset.contentAttr.split(",");

    mappings.forEach((mapping) => {
      const [path, attribute] = mapping.split(":").map((item) => item.trim());
      const value = getContentValue(content, path);

      if (path && attribute && typeof value === "string") {
        element.setAttribute(attribute, value);
        return;
      }

      if (path.endsWith(".link") && attribute === "href") {
        const linkText = getContentValue(content, path.replace(/\.link$/, ".linkText"));

        if (looksLikeUrl(linkText)) {
          element.setAttribute("href", linkText.trim());
        }
      }
    });
  });

  if (typeof content?.contact?.email === "string" && content.contact.email.includes("@")) {
    contactRecipient = content.contact.email.trim();
    document.querySelectorAll('a[href^="mailto:"]').forEach((link) => {
      link.setAttribute("href", `mailto:${contactRecipient}`);
    });
  }
};

fetch("data/content.json", { cache: "no-store" })
  .then((response) => (response.ok ? response.json() : null))
  .then((content) => {
    if (content) {
      applySiteContent(content);
    }
  })
  .catch(() => {
    // Keep the HTML fallback content when the JSON is unavailable locally.
  })
  .finally(applyInitialContactStatus);

menuToggle?.addEventListener("click", () => {
  const isOpen = nav.classList.toggle("is-open");
  document.body.classList.toggle("menu-open", isOpen);
  menuToggle.setAttribute("aria-expanded", String(isOpen));
});

nav?.addEventListener("click", (event) => {
  if (event.target.tagName !== "A") {
    return;
  }

  nav.classList.remove("is-open");
  document.body.classList.remove("menu-open");
  menuToggle?.setAttribute("aria-expanded", "false");
});

window.addEventListener("scroll", () => {
  header?.classList.toggle("is-scrolled", window.scrollY > 20);
});

contactForm?.addEventListener("submit", async (event) => {
  event.preventDefault();

  const submitButton = contactForm.querySelector('button[type="submit"]');
  const defaultButtonText = submitButton?.textContent || "Enviar mensagem";
  const formData = new FormData(contactForm);
  const payload = new URLSearchParams(formData);

  setFormMessage("Enviando mensagem...");

  if (submitButton) {
    submitButton.disabled = true;
    submitButton.textContent = "Enviando...";
  }

  try {
    const response = await fetch(contactForm.action, {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
      },
      body: payload.toString(),
    });
    const result = await response.json().catch(() => null);

    if (!response.ok || !result?.ok) {
      throw new Error("Contact request failed.");
    }

    contactForm.reset();
    setFormMessage("Mensagem enviada com sucesso.", "success");
  } catch (error) {
    setFormMessage("Não foi possível enviar agora. Tente novamente ou use os contatos diretos ao lado.", "error");
  } finally {
    if (submitButton) {
      submitButton.disabled = false;
      submitButton.textContent = defaultButtonText;
    }
  }
});
