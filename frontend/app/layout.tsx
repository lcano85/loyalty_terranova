import './globals.css';
import type { Metadata } from 'next';
export const metadata: Metadata = {title:'Terranova · Fidelización',description:'Tarjetas digitales de fidelización de Terranova Restobar'};
export default function Layout({children}:{children:React.ReactNode}) {return <html lang="es"><body>{children}</body></html>}
