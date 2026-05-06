const header = document.querySelector("[data-header]");
const menuToggle = document.querySelector("[data-menu-toggle]");
const nav = document.querySelector("[data-nav]");
const form = document.querySelector("[data-contact-form]");
const formNote = document.querySelector("[data-form-note]");
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
  });

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

form?.addEventListener("submit", (event) => {
  event.preventDefault();

  const data = new FormData(form);
  const nome = data.get("nome")?.toString().trim() || "";
  const email = data.get("email")?.toString().trim() || "";
  const telefone = data.get("telefone")?.toString().trim() || "";
  const mensagem = data.get("mensagem")?.toString().trim() || "";

  const subject = encodeURIComponent(`Contato pelo site - ${nome}`);
  const body = encodeURIComponent(
    `Nome: ${nome}\nEmail: ${email}\nTelefone: ${telefone}\n\nMensagem:\n${mensagem}`
  );

  window.location.href = `mailto:${contactRecipient}?subject=${subject}&body=${body}`;

  if (formNote) {
    formNote.textContent = "Mensagem preparada no seu cliente de email.";
  }
});
