import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="24" cy="24" r="15" stroke="currentColor" strokeWidth="2.6" fill="none" />
            <circle cx="24" cy="9" r="3.5" fill="currentColor" />
            <circle cx="11" cy="31.5" r="3.5" fill="currentColor" />
            <circle cx="37" cy="31.5" r="3.5" fill="currentColor" />
            <circle cx="24" cy="24" r="3.6" fill="#ff6a21" />
        </svg>
    );
}
