import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { FIRMA_DEFECTO, WebcafeinaFooter } from './WebcafeinaFooter';

describe('WebcafeinaFooter', () => {
    it('renders the default signature with the link on Webcafeína', () => {
        const { container } = render(<WebcafeinaFooter text={FIRMA_DEFECTO} />);

        expect(container.textContent).toBe(FIRMA_DEFECTO);
        const link = screen.getByRole('link', { name: 'Webcafeína' });
        expect(link).toHaveAttribute('href', 'https://webcafeina.com');
    });

    it('renders custom text without a link when it does not mention Webcafeína', () => {
        const { container } = render(<WebcafeinaFooter text="Gestor de reservas de AldeaLab" />);

        expect(container.textContent).toBe('Gestor de reservas de AldeaLab');
        expect(screen.queryByRole('link')).toBeNull();
    });

    it.each([null, '', '   '])('renders nothing for %j', (text: string | null) => {
        const { container } = render(<WebcafeinaFooter text={text} />);

        expect(container).toBeEmptyDOMElement();
    });
});
