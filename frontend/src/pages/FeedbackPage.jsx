import { useState } from "react";
import { apiBaseUrl } from "../api";

const initialForm = {
  name: "",
  email: "",
  rating: "",
  improvements: "",
  newFeatures: "",
};

export default function FeedbackPage() {
  const [form, setForm] = useState(initialForm);
  const [result, setResult] = useState(null);
  const [error, setError] = useState("");
  const [submitting, setSubmitting] = useState(false);

  function update(changes) {
    setForm((prev) => ({ ...prev, ...changes }));
  }

  async function submitFeedback(event) {
    event.preventDefault();
    setError("");
    setResult(null);

    if (!form.improvements.trim() && !form.newFeatures.trim()) {
      setError("Please share at least one improvement or a new feature idea.");
      return;
    }

    setSubmitting(true);
    try {
      const response = await fetch(`${apiBaseUrl}/api/feedback`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          name: form.name,
          email: form.email,
          rating: form.rating === "" ? null : Number(form.rating),
          improvements: form.improvements,
          newFeatures: form.newFeatures,
        }),
      });

      const data = await response.json();
      if (!response.ok) throw new Error(data.error || "Unable to submit feedback");
      setResult(data);
      setForm(initialForm);
    } catch (err) {
      setError(err.message);
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <section className="card">
      <h2>Share Your Feedback</h2>
      <p className="muted">
        Help us make Swiftcart better. Tell us what could be improved and which new features you
        would love to see.
      </p>
      <form onSubmit={submitFeedback}>
        <label>Name (optional)</label>
        <input
          value={form.name}
          onChange={(e) => update({ name: e.target.value })}
          placeholder="Jane Doe"
        />

        <label>Email (optional)</label>
        <input
          type="email"
          value={form.email}
          onChange={(e) => update({ email: e.target.value })}
          placeholder="jane@example.com"
        />

        <label>Overall Rating</label>
        <select value={form.rating} onChange={(e) => update({ rating: e.target.value })}>
          <option value="">No rating</option>
          <option value="5">★★★★★ — Excellent</option>
          <option value="4">★★★★ — Good</option>
          <option value="3">★★★ — Average</option>
          <option value="2">★★ — Poor</option>
          <option value="1">★ — Very poor</option>
        </select>

        <label>What aspects can be improved?</label>
        <textarea
          rows="4"
          value={form.improvements}
          onChange={(e) => update({ improvements: e.target.value })}
          placeholder="e.g. faster loading, clearer error messages, better mobile layout…"
        />

        <label>What new features would enhance this app?</label>
        <textarea
          rows="4"
          value={form.newFeatures}
          onChange={(e) => update({ newFeatures: e.target.value })}
          placeholder="e.g. export orders to CSV, customer accounts, discount codes…"
        />

        <button type="submit" disabled={submitting}>
          {submitting ? "Submitting…" : "Submit Feedback"}
        </button>
      </form>

      {error ? <div className="message error">{error}</div> : null}
      {result ? (
        <div className="feedback-success" role="status">
          <span className="feedback-success-icon" aria-hidden="true">
            🎉
          </span>
          <div>
            <strong>Thank you!</strong>
            <p>{result.message}</p>
          </div>
        </div>
      ) : null}
    </section>
  );
}
