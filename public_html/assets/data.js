
// Par défaut : quelques fruits/légumes (tu peux éditer via admin)
const DEFAULT_PRODUCTS = [
  { id:"fraise", name:"Fraise", category:"Fruits", origin:"Belgique",
    image:"https://images.unsplash.com/photo-1498601761256-94f05c0b6f30?q=80&w=800&auto=format&fit=crop",
    price:5.95, unit:"Barquette 500g", tags:["Printemps","Été"], featured:true, created:"2025-04-10" },
  { id:"cerise", name:"Cerise", category:"Fruits", origin:"France / Espagne",
    image:"https://images.unsplash.com/photo-1464965911861-746a04b4bca6?q=80&w=800&auto=format&fit=crop",
    price:4.95, unit:"500g", tags:["Été"], created:"2025-06-05" },
  { id:"melon", name:"Melon Charentais", category:"Fruits", origin:"France / Espagne",
    image:"https://images.unsplash.com/photo-1464961968964-a80a9b51f3a4?q=80&w=800&auto=format&fit=crop",
    price:3.95, unit:"1 pièce", oldPrice:7.90, tags:["Été"], featured:true, created:"2025-06-18" },
  { id:"banane", name:"Banane", category:"Fruits", origin:"Colombie",
    image:"https://images.unsplash.com/photo-1571772805064-207c8435df79?q=80&w=800&auto=format&fit=crop",
    price:1.95, unit:"~1kg (5–6 pièces)", tags:["Toute l'année"], created:"2025-01-12" },
  { id:"tomate", name:"Tomate grappe", category:"Légumes", origin:"France",
    image:"https://images.unsplash.com/photo-1546094096-0df4bcaaa337?q=80&w=800&auto=format&fit=crop",
    price:3.20, unit:"1kg", tags:["Été"], created:"2025-05-12" },
  { id:"courgette", name:"Courgette", category:"Légumes", origin:"Espagne",
    image:"https://images.unsplash.com/photo-1598033129183-c4f50c736f10?q=80&w=800&auto=format&fit=crop",
    price:2.30, unit:"1kg", tags:["Printemps","Été"], created:"2025-05-20" },
  { id:"pomme-de-terre", name:"Pomme de terre", category:"Légumes", origin:"France",
    image:"https://images.unsplash.com/photo-1518977676601-b53f82aba655?q=80&w=800&auto=format&fit=crop",
    price:1.80, unit:"2.5kg", tags:["Toute l'année"], created:"2025-01-10" }
];
