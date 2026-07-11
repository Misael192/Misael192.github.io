import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "PeopleFlow — Plataforma de RH e Departamento Pessoal",
  description:
    "DP, RH, ponto, férias, admissão digital, recrutamento e IA — tudo em uma plataforma multiempresa moderna e segura.",
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="pt-BR" suppressHydrationWarning>
      <head>
        {/* Aplica o tema salvo antes da hidratação para evitar flash. */}
        <script
          dangerouslySetInnerHTML={{
            __html: `try{var t=localStorage.getItem("pf-theme");if(t)document.documentElement.dataset.theme=t}catch(e){}`,
          }}
        />
      </head>
      <body className="font-sans antialiased">{children}</body>
    </html>
  );
}
