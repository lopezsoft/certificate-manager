<ext:UBLExtension>
    <ext:ExtensionContent>
        <CustomTagGeneral>
            <Name>Responsable</Name>
            <Value>url www.minSalud.gov.co</Value>
            <Name>Tipo, identificador:año del acto administrativo</Name>
            <Value>Resolución 084:2021</Value>
            <Interoperabilidad>
                <Group schemeName="Sector Salud">
                    @foreach($health->user_collections as $userCollection)
                    <Collection schemeName="Usuario">
                        @foreach($userCollection['information'] as $information)
                        <AdditionalInformation>
                            <Name>{{$information['name']}}</Name>
                            <Value @if(isset($information['schemeName']))schemeName="{{$information['schemeName']}}" @endif
                            @if(isset($information['schemeID'])) schemeID="{{$information['schemeID']}}" @endif>{{$information['value']}}</Value>
                        </AdditionalInformation>
                        @endforeach
                    </Collection>
                    @endforeach
                </Group>
                @if(isset($health->download_attachments) || isset($health->document_delivery))
                <InteroperabilidadPT>
                    @if(isset($health->download_attachments))
                    <URLDescargaAdjuntos>
                        <URL>{{$health->download_attachments->url}}</URL>
                        <ParametrosArgumentos>
                        @foreach($health->download_attachments->arguments as $argument)
                            <ParametroArgumento>
                                <Name>{{$argument['name']}}</Name>
                                <Value>{{$argument['value']}}</Value>
                            </ParametroArgumento>
                        @endforeach
                        </ParametrosArgumentos>
                    </URLDescargaAdjuntos>
                    @endif
                    @if(isset($health->document_delivery))
                    <EntregaDocumento>
                        <WS>{{$health->document_delivery->ws}}</WS>
                        <ParametrosArgumentos>
                        @foreach($health->document_delivery->arguments as $argument)
                            <ParametroArgumento>
                                <Name>{{$argument['name']}}</Name>
                                <Value>{{$argument['value']}}</Value>
                            </ParametroArgumento>
                        @endforeach
                        </ParametrosArgumentos>
                    </EntregaDocumento>
                    @endif
                </InteroperabilidadPT>
               @endif
            </Interoperabilidad>
        </CustomTagGeneral>
    </ext:ExtensionContent>
</ext:UBLExtension>
