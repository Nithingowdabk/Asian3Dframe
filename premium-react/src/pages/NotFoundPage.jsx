import { Link } from 'react-router-dom';

export default function NotFoundPage() {
  return (
    <section className="shell state-box">
      <h1>Page not found</h1>
      <Link className="btn-solid" to="/">Go Home</Link>
    </section>
  );
}
