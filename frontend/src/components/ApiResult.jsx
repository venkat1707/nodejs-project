export default function ApiResult({ data, error }) {
  if (error) return <div className="message error">{error}</div>;
  if (!data) return null;
  return <pre className="message">{JSON.stringify(data, null, 2)}</pre>;
}
